(function() {
	const { createElement: h, render, Fragment, useState, useMemo } = wp.element;
	const { __ } = wp.i18n;
	const { Button, Card, CardBody, CardHeader, Notice, SelectControl, TextControl, Flex, FlexItem, Spinner, DatePicker, PanelBody, PanelRow, Modal } = wp.components;

	function useInitialState() {
		const container = document.getElementById('iyzico-subscription-app');
		let initial = { filters: {}, stats: {}, subscriptions: [], links: {} };
		if (container && container.dataset.initialState) {
			try { initial = JSON.parse(container.dataset.initialState); } catch(e) {}
		}
		return initial;
	}

	function request(action, data) {
		const cfg = window.iyzicoSubscriptionAdmin || {};
		const payload = Object.assign({ action: action, nonce: cfg.nonce }, data || {});
		return new Promise(function(resolve) {
			fetch(cfg.ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: new URLSearchParams(payload).toString()
			}).then(function(r){ return r.json(); }).then(resolve).catch(function(){ resolve({ success:false }); });
		});
	}

	function StatsGrid({ stats }) {
		const items = [
			{ key: 'total', label: iyzicoSubscriptionAdmin.i18n.totalSubscriptions || __('Toplam Abonelik', 'iyzico-subscription'), value: stats && stats.total ? stats.total : 0 },
			{ key: 'active', label: iyzicoSubscriptionAdmin.i18n.active || __('Aktif', 'iyzico-subscription'), value: stats && stats.active ? stats.active : 0 },
			{ key: 'suspended', label: iyzicoSubscriptionAdmin.i18n.suspended || __('Askıda', 'iyzico-subscription'), value: stats && stats.suspended ? stats.suspended : 0 },
			{ key: 'monthly_revenue', label: iyzicoSubscriptionAdmin.i18n.monthlyRevenue || __('Aylık Gelir', 'iyzico-subscription'), value: (stats && (stats.monthly_revenue_formatted || stats.monthly_revenue)) ? (stats.monthly_revenue_formatted || stats.monthly_revenue) : 0 }
		];
		return h(Flex, { gap: 12 }, items.map(function(item) {
			var content = (typeof item.value === 'string' && item.value.indexOf('<') !== -1)
				? h('span', { dangerouslySetInnerHTML: { __html: item.value } })
				: String(item.value);
			return h(FlexItem, { key: item.key, style: { minWidth: 200 } },
				h(Card, {}, [
					h(CardHeader, {}, item.label),
					h(CardBody, { style: { fontSize: 22, fontWeight: 600 } }, content)
				])
			);
		}));
	}

	function Filters({ value, onChange, onSubmit, onReset }) {
		return h(Card, {}, [
			h(CardHeader, {}, iyzicoSubscriptionAdmin.i18n.filters),
			h(CardBody, {},
				h(Flex, { gap: 12, align: 'center' }, [
					h(FlexItem, { style: { minWidth: 200 } }, h(SelectControl, {
						label: iyzicoSubscriptionAdmin.i18n.status,
						value: value.status || '',
						options: [
							{ label: iyzicoSubscriptionAdmin.i18n.allStatuses, value: '' },
							{ label: iyzicoSubscriptionAdmin.i18n.active, value: 'active' },
							{ label: iyzicoSubscriptionAdmin.i18n.suspended, value: 'suspended' },
							{ label: iyzicoSubscriptionAdmin.i18n.cancelled, value: 'cancelled' },
							{ label: iyzicoSubscriptionAdmin.i18n.expired, value: 'expired' },
						]
					})),
					h(FlexItem, { style: { minWidth: 220 } }, h(TextControl, {
						label: iyzicoSubscriptionAdmin.i18n.customer,
						placeholder: iyzicoSubscriptionAdmin.i18n.searchCustomer,
						value: value.customer_search || '',
						onChange: function(v){ onChange(Object.assign({}, value, { customer_search: v })); }
					})),
					h(FlexItem, { style: { minWidth: 220 } }, h(TextControl, {
						label: iyzicoSubscriptionAdmin.i18n.dateFrom,
						type: 'date',
						value: value.date_from || '',
						onChange: function(v){ onChange(Object.assign({}, value, { date_from: v })); }
					})),
					h(FlexItem, { style: { minWidth: 220 } }, h(TextControl, {
						label: iyzicoSubscriptionAdmin.i18n.dateTo,
						type: 'date',
						value: value.date_to || '',
						onChange: function(v){ onChange(Object.assign({}, value, { date_to: v })); }
					})),
					h(FlexItem, {}, h(Button, { variant: 'primary', onClick: onSubmit }, iyzicoSubscriptionAdmin.i18n.apply)),
					h(FlexItem, {}, h(Button, { isSecondary: true, onClick: onReset }, __('Temizle', 'iyzico-subscription')))
				])
			)
		]);
	}

	function ActionsCell({ subscription, onAction, onSavedCards }) {
		const actions = [];
		if (subscription.status === 'active') {
			actions.push({ key: 'suspend', label: iyzicoSubscriptionAdmin.i18n.suspend, action: 'suspend', icon: 'dashicons-controls-pause' });
			actions.push({ key: 'cancel', label: iyzicoSubscriptionAdmin.i18n.cancel, action: 'cancel', icon: 'dashicons-no' });
		} else if (subscription.status === 'suspended') {
			actions.push({ key: 'reactivate', label: iyzicoSubscriptionAdmin.i18n.reactivate, action: 'reactivate', icon: 'dashicons-update' });
		} else if (subscription.status === 'cancelled') {
			actions.push({ key: 'reactivate', label: iyzicoSubscriptionAdmin.i18n.reactivate, action: 'reactivate', icon: 'dashicons-update' });
		}
		if (subscription.customer_id) {
			actions.push({ key: 'savedCards', label: iyzicoSubscriptionAdmin.i18n.savedCards, action: 'savedCards', icon: 'dashicons-credit-card' });
		}
		return h(Flex, { gap: 8 }, actions.map(function(a) {
			return h(Button, {
				key: a.key,
				isSmall: true,
				isSecondary: true,
				onClick: function(){ a.action === 'savedCards' ? onSavedCards(subscription) : onAction(subscription.id, a.action); },
				title: a.label,
				'aria-label': a.label
			}, h('span', { className: 'dashicons ' + a.icon }));
		}));
	}

	function Table({ items, onAction, onSavedCards }) {
		if (!items || !items.length) {
			return h(Card, {}, [
				h(CardBody, {}, [
					h('p', {}, iyzicoSubscriptionAdmin.i18n.noItems),
					h(Button, { href: (window.iyzicoInitial && window.iyzicoInitial.links && window.iyzicoInitial.links.newProduct) || '#', variant:'primary' }, iyzicoSubscriptionAdmin.i18n.createFirst)
				])
			]);
		}
		var statusLabel = function(status){
			var map = {
				'active': iyzicoSubscriptionAdmin.i18n.active,
				'suspended': iyzicoSubscriptionAdmin.i18n.suspended,
				'cancelled': iyzicoSubscriptionAdmin.i18n.cancelled,
				'expired': iyzicoSubscriptionAdmin.i18n.expired
			};
			return map[status] || status;
		};
		var periodLabel = function(period){
			var map = { day: 'Günlük', week: 'Haftalık', month: 'Aylık', year: 'Yıllık' };
			return map[period] || period || '';
		};
		return h('table', { className: 'wp-list-table widefat fixed striped' }, [
			h('thead', {}, h('tr', {}, [
				h('th', {}, iyzicoSubscriptionAdmin.i18n.id),
				h('th', {}, iyzicoSubscriptionAdmin.i18n.customer),
				h('th', {}, iyzicoSubscriptionAdmin.i18n.product),
				h('th', {}, iyzicoSubscriptionAdmin.i18n.status),
				h('th', {}, iyzicoSubscriptionAdmin.i18n.amount),
				h('th', {}, iyzicoSubscriptionAdmin.i18n.period),
				h('th', {}, iyzicoSubscriptionAdmin.i18n.startDate),
				h('th', {}, iyzicoSubscriptionAdmin.i18n.nextPayment),
				h('th', {}, iyzicoSubscriptionAdmin.i18n.actions)
			])),
			h('tbody', {}, items.map(function(s){
				return h('tr', { key: s.id }, [
					h('td', {}, '#' + s.id),
					h('td', {}, s.customer_name || ''),
					h('td', {}, s.product_name || ''),
					h('td', {}, h('span', { className: 'iyzico-status-badge iyzico-status-' + (s.status || '').toLowerCase(), title: statusLabel(s.status) }, statusLabel(s.status))),
					h('td', {}, s.amount ? h('span', { className: 'iyzico-amount', dangerouslySetInnerHTML: { __html: s.amount } }) : ''),
					h('td', {}, h('span', { className: 'iyzico-period-badge', title: periodLabel(s.period) }, [ h('span', { className: 'dashicons dashicons-calendar-alt', style: { marginRight: 4 } }), periodLabel(s.period) ])),
					h('td', {}, s.start_date ? s.start_date : ''),
					h('td', {}, s.next_payment ? s.next_payment : ''),
					h('td', {}, h(ActionsCell, { subscription: s, onAction, onSavedCards }))
				]);
			}))
		]);
	}

	function SavedCardsModal({ subscription, onRequestClose }) {
		const [cards, setCards] = useState(null);
		const [error, setError] = useState(null);
		const [isCreating, setIsCreating] = useState(false);
		const [form, setForm] = useState({ card_alias: '', card_holder_name: '', card_number: '', expire_month: '', expire_year: '' });

		function list() {
			setError(null);
			setCards(null);
			request('iyzico_admin_list_saved_cards', { user_id: subscription.customer_id })
				.then(function(res){
					if (res && res.success) {
						setCards(res.data && res.data.cards ? res.data.cards : []);
					} else {
						setError((res && res.data && res.data.message) || iyzicoSubscriptionAdmin.i18n.error);
						setCards([]);
					}
				});
		}

		function createCard() {
			setIsCreating(true);
			setError(null);
			const payload = Object.assign({ user_id: subscription.customer_id }, form);
			request('iyzico_admin_create_saved_card', payload)
				.then(function(res){
					if (res && res.success) {
						list();
						alert(iyzicoSubscriptionAdmin.i18n.createdSuccess);
					} else {
						setError((res && res.data && res.data.message) || iyzicoSubscriptionAdmin.i18n.error);
					}
				})
				.finally(function(){ setIsCreating(false); });
		}

		if (cards === null && !error) {
			setTimeout(list, 0);
		}

		return h(Modal, { title: iyzicoSubscriptionAdmin.i18n.savedCards + ' - ' + (subscription.customer_name || ('#' + subscription.customer_id)), onRequestClose: onRequestClose }, [
			h('div', { style: { marginBottom: 12 } }, [
				h('h3', {}, iyzicoSubscriptionAdmin.i18n.savedCards),
				cards === null ? h(Spinner) : (
					(cards && cards.length) ? h('ul', {}, cards.map(function(c, i){
						return h('li', { key: i }, [
							(c.alias ? (c.alias + ' - ') : ''),
							(c.bank ? (c.bank + ' ') : ''),
							(c.family ? (c.family + ' ') : ''),
							(c.type ? (c.type + ' ') : ''),
							(c.bin ? (c.bin + ' ') : ''),
							(c.lastFour ? ('**** ' + c.lastFour) : '')
						]);
					})) : h('p', {}, iyzicoSubscriptionAdmin.i18n.noSavedCards)
				),
				error ? h(Notice, { status: 'error', isDismissible: true, onRemove: function(){ setError(null); } }, error) : null
			]),
			h('div', { style: { borderTop: '1px solid #eee', paddingTop: 12, marginTop: 12 } }, [
				h('h3', {}, iyzicoSubscriptionAdmin.i18n.addNewCard),
				h(Flex, { gap: 8, align: 'center', wrap: 'wrap' }, [
					h(FlexItem, { style: { minWidth: 180 } }, h(TextControl, { label: iyzicoSubscriptionAdmin.i18n.cardAlias, value: form.card_alias, onChange: function(v){ setForm(Object.assign({}, form, { card_alias: v })); } })),
					h(FlexItem, { style: { minWidth: 220 } }, h(TextControl, { label: iyzicoSubscriptionAdmin.i18n.cardHolderName, value: form.card_holder_name, onChange: function(v){ setForm(Object.assign({}, form, { card_holder_name: v })); } })),
					h(FlexItem, { style: { minWidth: 220 } }, h(TextControl, { label: iyzicoSubscriptionAdmin.i18n.cardNumber, value: form.card_number, onChange: function(v){ setForm(Object.assign({}, form, { card_number: v })); }, type: 'text' })),
					h(FlexItem, { style: { minWidth: 120 } }, h(TextControl, { label: iyzicoSubscriptionAdmin.i18n.expireMonth, value: form.expire_month, onChange: function(v){ setForm(Object.assign({}, form, { expire_month: v })); }, placeholder: 'MM' })),
					h(FlexItem, { style: { minWidth: 140 } }, h(TextControl, { label: iyzicoSubscriptionAdmin.i18n.expireYear, value: form.expire_year, onChange: function(v){ setForm(Object.assign({}, form, { expire_year: v })); }, placeholder: 'YYYY' })),
					h(FlexItem, {}, h(Button, { variant: 'primary', disabled: isCreating, onClick: createCard }, isCreating ? h(Spinner) : iyzicoSubscriptionAdmin.i18n.create))
				])
			])
		]);
	}

	function App() {
		const init = useInitialState();
		window.iyzicoInitial = init;
		const [filters, setFilters] = useState(init.filters || {});
		const [stats, setStats] = useState(init.stats || {});
		const [items, setItems] = useState(init.subscriptions || []);
		const [isWorking, setIsWorking] = useState(false);
		const [notice, setNotice] = useState(null);
		const [savedCardsFor, setSavedCardsFor] = useState(null);

		function refresh() {
			// On initial version, full reload ensures parity with existing PHP data builders
			window.location.href = addQueryArg(window.location.href, filters);
		}

		function addQueryArg(url, args) {
			const u = new URL(url, window.location.origin);
			Object.keys(args || {}).forEach(function(k){
				if (args[k] === '' || args[k] === undefined || args[k] === null) {
					u.searchParams.delete(k);
				} else {
					u.searchParams.set(k, args[k]);
				}
			});
			return u.toString();
		}

		function handleAction(id, action) {
			if (!window.confirm((iyzicoSubscriptionAdmin.i18n && iyzicoSubscriptionAdmin.i18n.confirmAction) || 'Emin misiniz?')) return;
			setIsWorking(true);
			request('iyzico_subscription_admin_action', { subscription_id: id, subscription_action: action })
				.then(function(res){
					if (res && res.success) {
						setNotice({ status: 'success', msg: iyzicoSubscriptionAdmin.i18n.success });
						setTimeout(function(){ window.location.reload(); }, 800);
					} else {
						setNotice({ status: 'error', msg: (res && res.data && res.data.message) || iyzicoSubscriptionAdmin.i18n.error });
					}
				})
				.finally(function(){ setIsWorking(false); });
		}

		return h(Fragment, {}, [
			h('div', { className: 'wrap' }, [
				h('h1', { className: 'wp-heading-inline' }, iyzicoSubscriptionAdmin.i18n.title),
				h(Button, { href: init.links && init.links.newProduct, className: 'page-title-action' }, iyzicoSubscriptionAdmin.i18n.newProduct),
				h('hr', { className: 'wp-header-end' })
			]),
			notice ? h(Notice, { status: notice.status, onRemove: function(){ setNotice(null); }, isDismissible: true }, notice.msg) : null,
			h(StatsGrid, { stats }),
			h('div', { style: { marginTop: 12 } }, h(Filters, {
				value: filters,
				onChange: setFilters,
				onSubmit: refresh,
				onReset: function(){ setFilters({}); setTimeout(refresh, 0); }
			})),
			h('div', { style: { marginTop: 12, position: 'relative' } }, [
				isWorking ? h('div', { style: { position:'absolute', inset:0, background:'rgba(255,255,255,0.6)', display:'flex', alignItems:'center', justifyContent:'center', zIndex: 1 } }, h(Spinner)) : null,
				h(Table, { items, onAction: handleAction, onSavedCards: function(s){ setSavedCardsFor(s); } })
			]),
			savedCardsFor ? h(SavedCardsModal, { subscription: savedCardsFor, onRequestClose: function(){ setSavedCardsFor(null); } }) : null
		]);
	}

	function mount() {
		const root = document.getElementById('iyzico-subscription-app');
		if (!root) return;
		render(h(App), root);
	}

	document.addEventListener('DOMContentLoaded', mount);
})();