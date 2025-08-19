const settings = window.wc.wcSettings.getSetting('iyzico_subscription_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('iyzico Abonelik', 'iyzico-subscription');

// Display checkout error passed via URL for WooCommerce Blocks
(function(){
    try {
        var url = new URL(window.location.href);
        var err = url.searchParams.get('iyzico_error');
        if (!err) return;
        var code = url.searchParams.get('error_code');
        var msg = url.searchParams.get('error_message');
        var __ = window.wp.i18n.__;
        var message = '';
        switch (err) {
            case 'card_info_missing':
                message = __('Kart bilgileri alınamadığı için ödeme iptal edildi. Lütfen tekrar deneyiniz.', 'iyzico-subscription');
                break;
            case 'cancel_failed':
                message = (__('Ödeme iptal edilemedi. Hata Kodu: %1$s, Hata: %2$s', 'iyzico-subscription') || '').replace('%1$s', code || '').replace('%2$s', decodeURIComponent(msg || ''));
                break;
            case 'payment_failed':
                message = __('Ödeme başarısız oldu. Lütfen tekrar deneyiniz.', 'iyzico-subscription');
                break;
            case 'general_error':
                message = (__('İşlem sırasında bir hata oluştu: %1$s', 'iyzico-subscription') || '').replace('%1$s', decodeURIComponent(msg || ''));
                break;
            default:
                message = __('Bilinmeyen bir hata oluştu. Lütfen tekrar deneyiniz.', 'iyzico-subscription');
        }
        if (message && window.wp && window.wp.data && window.wp.data.dispatch) {
            var notices = window.wp.data.dispatch('core/notices');
            if (notices && notices.createErrorNotice) {
                notices.createErrorNotice(message, { isDismissible: true });
            }
        }
    } catch(e) {}
})();

const Content = () => {
    return window.wp.htmlEntities.decodeEntities(settings.description || '');
};

const Block_Gateway = {
    name: 'iyzico_subscription',
    label: label,
    content: Object(window.wp.element.createElement)(Content, null),
    edit: Object(window.wp.element.createElement)(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);