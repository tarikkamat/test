/**
 * Iyzico Subscription Admin - Minimal WordPress-compatible script
 * - No Bootstrap
 * - No custom table/filter/pagination logic
 * - Handles: individual actions and bulk actions via AJAX
 */

(function($) {
    'use strict';

    function getConfig() {
        if (typeof window.iyzicoSubscriptionAdmin === 'undefined') {
            console.error('iyzicoSubscriptionAdmin not found');
            return null;
        }
        return window.iyzicoSubscriptionAdmin;
    }

    function showNotice(type, message) {
        // type: success | error | warning | info
        var cls = 'notice';
        if (type === 'success') cls += ' notice-success';
        else if (type === 'error') cls += ' notice-error';
        else if (type === 'warning') cls += ' notice-warning';
        else cls += ' notice-info';

        var $notice = $('<div/>', {
            class: cls + ' is-dismissible',
            html: '<p>' + message + '</p>'
        });

        var $container = $('.wrap');
        if ($container.length) {
            $container.prepend($notice);
        } else {
            $('body').prepend($notice);
        }

        // WordPress dismissible behavior
        $notice.on('click', '.notice-dismiss', function() {
            $notice.remove();
        });
    }

    function request(action, data) {
        var cfg = getConfig();
        if (!cfg) return $.Deferred().reject('config-missing').promise();
        var payload = $.extend({
            action: action,
            nonce: cfg.nonce
        }, data || {});
        return $.post(cfg.ajaxurl, payload, null, 'json');
    }

    function confirmMessageFor(action) {
        var cfg = getConfig() || { i18n: {} };
        var i18n = cfg.i18n || {};
        // Fallback to generic confirm if specific not provided
        var defaults = {
            suspend: i18n.confirmSuspend || i18n.confirmAction || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?',
            cancel: i18n.confirmCancel || i18n.confirmAction || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?',
            reactivate: i18n.confirmReactivate || i18n.confirmAction || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?'
        };
        return defaults[action] || (i18n.confirmAction || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?');
    }

    function handleIndividualAction($button) {
        var action = $button.data('action');
        var subscriptionId = $button.data('subscription-id');
        if (!action || !subscriptionId) return;

        if (!window.confirm(confirmMessageFor(action))) return;

        var originalHtml = $button.html();
        $button.prop('disabled', true).html('<span class="spinner is-active" style="float:none;visibility:visible;margin:0"></span>');

        request('iyzico_subscription_admin_action', {
            subscription_id: subscriptionId,
            subscription_action: action
        })
        .done(function(res) {
            if (res && res.success) {
                showNotice('success', (res.data && res.data.message) || 'İşlem başarıyla tamamlandı.');
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                showNotice('error', (res && res.data && res.data.message) || 'İşlem başarısız oldu.');
            }
        })
        .fail(function() {
            showNotice('error', 'İşlem sırasında bir hata oluştu.');
        })
        .always(function() {
            $button.prop('disabled', false).html(originalHtml);
        });
    }

    function getSelectedIds() {
        return $('input[name="subscription[]"]:checked').map(function() {
            return $(this).val();
        }).get();
    }

    function handleBulkAction(fromBottom) {
        var selectId = fromBottom ? '#bulk-action-selector-bottom' : '#bulk-action-selector-top';
        var action = $(selectId).val();
        if (!action || action === '-1') {
            showNotice('warning', 'Lütfen bir toplu işlem seçin.');
            return;
        }
        var ids = getSelectedIds();
        if (!ids.length) {
            showNotice('warning', 'Lütfen en az bir abonelik seçin.');
            return;
        }

        if (!window.confirm('Seçili ' + ids.length + ' aboneliğe "' + action + '" uygulanacak. Devam etmek istiyor musunuz?')) {
            return;
        }

        var completed = 0, successCount = 0;
        var $buttons = $('#doaction, #doaction2').prop('disabled', true);

        ids.forEach(function(id) {
            request('iyzico_subscription_admin_action', {
                subscription_id: id,
                subscription_action: action
            })
            .done(function(res) {
                if (res && res.success) successCount++;
            })
            .always(function() {
                completed++;
                if (completed === ids.length) {
                    $buttons.prop('disabled', false);
                    var msg = successCount + ' abonelik işlendi';
                    if (successCount < ids.length) {
                        msg += ', ' + (ids.length - successCount) + ' abonelikte hata oluştu';
                        showNotice('warning', msg);
                    } else {
                        showNotice('success', msg);
                    }
                    setTimeout(function() { window.location.reload(); }, 800);
                }
            });
        });
    }

    $(document).ready(function() {
        if (!getConfig()) return;

        // Individual actions (links or buttons with .subscription-action)
        $(document).on('click', '.subscription-action', function(e) {
            e.preventDefault();
            handleIndividualAction($(this));
        });

        // Bulk actions (native list table controls)
        $(document).on('click', '#doaction', function(e) {
            e.preventDefault();
            handleBulkAction(false);
        });
        $(document).on('click', '#doaction2', function(e) {
            e.preventDefault();
            handleBulkAction(true);
        });
    });

})(jQuery);

/**
 * Iyzico Subscription Admin JavaScript
 * Modern, performanslı ve kullanıcı dostu admin arayüzü
 */

(function($) {
    'use strict';

    // Global namespace
    window.IyzicoAdmin = {
        // Configuration
        config: {
            ajaxUrl: '',
            nonce: '',
            i18n: {},
            selectors: {
                statsGrid: '.iyzico-stats-grid',
                filtersSection: '.iyzico-filters-section',
                tableContainer: '.iyzico-table-container',
                bulkActions: '.iyzico-bulk-actions',
                pagination: '.iyzico-pagination'
            }
        },

        // State management
        state: {
            isLoading: false,
            selectedItems: new Set(),
            currentFilters: {},
            currentPage: 1,
            itemsPerPage: 20
        },

        // Initialize the admin interface
        init: function() {
            this.setupEventListeners();
            this.initializeComponents();
            this.setupKeyboardShortcuts();
            this.animateStats();
            
            console.log('Iyzico Admin initialized successfully');
        },

        // Setup all event listeners
        setupEventListeners: function() {
            // Bulk selection
            this.setupBulkSelection();
            
            // Action buttons
            this.setupActionButtons();
            
            // Filters
            this.setupFilters();
            
            // Table interactions
            this.setupTableInteractions();
            
            // Pagination
            this.setupPagination();
            
            // Search functionality
            this.setupSearch();
        },

        // Initialize UI components
        initializeComponents: function() {
            // Initialize tooltips if Bootstrap is available
            if (typeof bootstrap !== 'undefined') {
                this.initializeBootstrapComponents();
            }
            
            // Initialize custom components
            this.initializeCustomComponents();
        },

        // Bootstrap components initialization
        initializeBootstrapComponents: function() {
            // Tooltips
            const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipElements.forEach(element => {
                new bootstrap.Tooltip(element);
            });

            // Modals
            const modalElements = document.querySelectorAll('.modal');
            modalElements.forEach(modal => {
                modal.addEventListener('hidden.bs.modal', function() {
                    this.querySelector('.modal-body').scrollTop = 0;
                });
            });
        },

        // Custom components initialization
        initializeCustomComponents: function() {
            // Initialize filters toggle
            this.initializeFiltersToggle();
            
            // Initialize table sorting
            this.initializeTableSorting();
            
            // Initialize responsive table
            this.initializeResponsiveTable();
        },

        // Filters toggle functionality
        initializeFiltersToggle: function() {
            const toggleBtn = document.querySelector('.iyzico-filters-toggle');
            const filtersForm = document.querySelector('.iyzico-filters-form');
            
            if (toggleBtn && filtersForm) {
                toggleBtn.addEventListener('click', function() {
                    const isVisible = filtersForm.style.display !== 'none';
                    filtersForm.style.display = isVisible ? 'none' : 'grid';
                    toggleBtn.textContent = isVisible ? 'Filtreleri Göster' : 'Filtreleri Gizle';
                });
            }
        },

        // Table sorting functionality
        initializeTableSorting: function() {
            const sortableHeaders = document.querySelectorAll('.iyzico-table th[data-sort]');
            
            sortableHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const column = this.dataset.sort;
                    const direction = this.dataset.direction === 'asc' ? 'desc' : 'asc';
                    
                    // Update all headers
                    sortableHeaders.forEach(h => h.dataset.direction = '');
                    this.dataset.direction = direction;
                    
                    // Sort table
                    IyzicoAdmin.sortTable(column, direction);
                });
            });
        },

        // Responsive table functionality
        initializeResponsiveTable: function() {
            const table = document.querySelector('.iyzico-table');
            if (!table) return;

            // Add responsive wrapper
            const wrapper = document.createElement('div');
            wrapper.className = 'iyzico-table-responsive';
            wrapper.style.overflowX = 'auto';
            
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        },

        // Bulk selection setup
        setupBulkSelection: function() {
            // Master checkbox
            const masterCheckbox = document.getElementById('cb-select-all-1');
            if (masterCheckbox) {
                masterCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[name="subscription[]"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                        IyzicoAdmin.updateBulkSelection(checkbox);
                    });
                });
            }

            // Individual checkboxes
            document.addEventListener('change', function(e) {
                if (e.target.name === 'subscription[]') {
                    IyzicoAdmin.updateBulkSelection(e.target);
                }
            });
        },

        // Update bulk selection state
        updateBulkSelection: function(checkbox) {
            if (checkbox.checked) {
                this.state.selectedItems.add(checkbox.value);
            } else {
                this.state.selectedItems.delete(checkbox.value);
            }
            
            this.updateBulkActionsVisibility();
            this.updateMasterCheckbox();
        },

        // Update bulk actions visibility
        updateBulkActionsVisibility: function() {
            const bulkActions = document.querySelector('.iyzico-bulk-actions');
            if (bulkActions) {
                bulkActions.style.display = this.state.selectedItems.size > 0 ? 'flex' : 'none';
            }
        },

        // Update master checkbox state
        updateMasterCheckbox: function() {
            const masterCheckbox = document.getElementById('cb-select-all-1');
            if (!masterCheckbox) return;

            const totalCheckboxes = document.querySelectorAll('input[name="subscription[]"]').length;
            const checkedCheckboxes = this.state.selectedItems.size;

            if (checkedCheckboxes === 0) {
                masterCheckbox.checked = false;
                masterCheckbox.indeterminate = false;
            } else if (checkedCheckboxes === totalCheckboxes) {
                masterCheckbox.checked = true;
                masterCheckbox.indeterminate = false;
            } else {
                masterCheckbox.checked = false;
                masterCheckbox.indeterminate = true;
            }
        },

        // Action buttons setup
        setupActionButtons: function() {
            // Bulk action buttons
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('iyzico-btn') && e.target.dataset.action) {
                    e.preventDefault();
                    IyzicoAdmin.handleBulkAction(e.target);
                }
            });

            // Individual action buttons
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('subscription-action')) {
                    e.preventDefault();
                    IyzicoAdmin.handleIndividualAction(e.target);
                }
            });
        },

        // Handle bulk actions
        handleBulkAction: function(button) {
            const action = button.dataset.action;
            
            if (this.state.selectedItems.size === 0) {
                this.showNotification('warning', 'Lütfen en az bir abonelik seçin');
                return;
            }

            const confirmMessage = `Seçili ${this.state.selectedItems.size} aboneliğe "${action}" işlemi uygulanacak. Devam etmek istiyor musunuz?`;
            
            if (confirm(confirmMessage)) {
                this.performBulkAction(action, Array.from(this.state.selectedItems));
            }
        },

        // Handle individual actions
        handleIndividualAction: function(button) {
            const action = button.dataset.action;
            const subscriptionId = button.dataset.subscriptionId;
            
            const confirmMessages = {
                'suspend': 'Bu aboneliği askıya almak istediğinizden emin misiniz?',
                'cancel': 'Bu aboneliği iptal etmek istediğinizden emin misiniz? Bu işlem geri alınamaz.',
                'reactivate': 'Bu aboneliği yeniden aktifleştirmek istediğinizden emin misiniz?'
            };

            const confirmMessage = confirmMessages[action] || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?';
            
            if (confirm(confirmMessage)) {
                this.performIndividualAction(action, subscriptionId, button);
            }
        },

        // Perform bulk action
        performBulkAction: function(action, subscriptionIds) {
            this.setLoadingState(true);
            
            const promises = subscriptionIds.map(id => 
                this.makeAjaxRequest('iyzico_subscription_admin_action', {
                    subscription_id: id,
                    subscription_action: action
                })
            );

            Promise.all(promises)
                .then(responses => {
                    const successCount = responses.filter(r => r.success).length;
                    const errorCount = responses.length - successCount;
                    
                    let message = `${successCount} abonelik başarıyla işlendi`;
                    if (errorCount > 0) {
                        message += `, ${errorCount} abonelikte hata oluştu`;
                    }
                    
                    this.showNotification(successCount > 0 ? 'success' : 'error', message);
                    
                    if (successCount > 0) {
                        setTimeout(() => location.reload(), 2000);
                    }
                })
                .catch(error => {
                    console.error('Bulk action error:', error);
                    this.showNotification('error', 'Toplu işlem sırasında hata oluştu');
                })
                .finally(() => {
                    this.setLoadingState(false);
                });
        },

        // Perform individual action
        performIndividualAction: function(action, subscriptionId, button) {
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="iyzico-loading-spinner"></span>';

            this.makeAjaxRequest('iyzico_subscription_admin_action', {
                subscription_id: subscriptionId,
                subscription_action: action
            })
            .then(response => {
                if (response.success) {
                    this.showNotification('success', response.data.message || 'İşlem başarıyla tamamlandı');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.showNotification('error', response.data.message || 'İşlem sırasında hata oluştu');
                }
            })
            .catch(error => {
                console.error('Individual action error:', error);
                this.showNotification('error', 'İşlem sırasında hata oluştu');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            });
        },

        // Filters setup
        setupFilters: function() {
            // Date filters
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', () => this.applyFilters());
            });

            // Search input
            const searchInput = document.querySelector('input[name="customer_search"]');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        IyzicoAdmin.applyFilters();
                    }, 300);
                });
            }

            // Status filter
            const statusSelect = document.querySelector('select[name="status"]');
            if (statusSelect) {
                statusSelect.addEventListener('change', () => this.applyFilters());
            }

            // Filter form submission
            const filterForm = document.querySelector('.iyzico-filters-form');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    IyzicoAdmin.applyFilters();
                });
            }
        },

        // Apply filters
        applyFilters: function() {
            const filters = this.collectFilters();
            this.state.currentFilters = filters;
            this.state.currentPage = 1;
            
            this.performFilteredSearch(filters);
        },

        // Collect filter values
        collectFilters: function() {
            return {
                customer_search: document.querySelector('input[name="customer_search"]')?.value || '',
                status: document.querySelector('select[name="status"]')?.value || '',
                date_from: document.querySelector('input[name="date_from"]')?.value || '',
                date_to: document.querySelector('input[name="date_to"]')?.value || ''
            };
        },

        // Perform filtered search
        performFilteredSearch: function(filters) {
            this.setLoadingState(true);
            
            this.makeAjaxRequest('iyzico_subscription_filter', filters)
                .then(response => {
                    if (response.success) {
                        this.updateTableContent(response.data);
                    } else {
                        this.showNotification('error', 'Filtreleme sırasında hata oluştu');
                    }
                })
                .catch(error => {
                    console.error('Filter error:', error);
                    this.showNotification('error', 'Filtreleme sırasında hata oluştu');
                })
                .finally(() => {
                    this.setLoadingState(false);
                });
        },

        // Table interactions setup
        setupTableInteractions: function() {
            // Row selection
            document.addEventListener('click', function(e) {
                if (e.target.closest('.iyzico-table tbody tr')) {
                    const row = e.target.closest('tr');
                    IyzicoAdmin.toggleRowSelection(row);
                }
            });

            // Column sorting
            document.addEventListener('click', function(e) {
                if (e.target.closest('th[data-sort]')) {
                    const header = e.target.closest('th');
                    IyzicoAdmin.sortTable(header.dataset.sort, header.dataset.direction || 'asc');
                }
            });
        },

        // Toggle row selection
        toggleRowSelection: function(row) {
            row.classList.toggle('iyzico-row-selected');
        },

        // Sort table
        sortTable: function(column, direction) {
            const table = document.querySelector('.iyzico-table tbody');
            if (!table) return;

            const rows = Array.from(table.querySelectorAll('tr'));
            const columnIndex = this.getColumnIndex(column);
            
            if (columnIndex === -1) return;

            rows.sort((a, b) => {
                const aValue = a.cells[columnIndex].textContent.trim();
                const bValue = b.cells[columnIndex].textContent.trim();
                
                if (direction === 'asc') {
                    return aValue.localeCompare(bValue);
                } else {
                    return bValue.localeCompare(aValue);
                }
            });

            // Reorder rows
            rows.forEach(row => table.appendChild(row));
        },

        // Get column index by name
        getColumnIndex: function(columnName) {
            const headers = document.querySelectorAll('.iyzico-table th');
            for (let i = 0; i < headers.length; i++) {
                if (headers[i].dataset.sort === columnName) {
                    return i;
                }
            }
            return -1;
        },

        // Pagination setup
        setupPagination: function() {
            document.addEventListener('click', function(e) {
                if (e.target.closest('.iyzico-pagination-link')) {
                    e.preventDefault();
                    const link = e.target.closest('.iyzico-pagination-link');
                    const page = link.dataset.page;
                    IyzicoAdmin.goToPage(parseInt(page));
                }
            });
        },

        // Go to specific page
        goToPage: function(page) {
            this.state.currentPage = page;
            this.loadPage(page);
        },

        // Load specific page
        loadPage: function(page) {
            const filters = this.state.currentFilters;
            filters.page = page;
            
            this.performFilteredSearch(filters);
        },

        // Search functionality setup
        setupSearch: function() {
            const searchInput = document.querySelector('input[name="customer_search"]');
            if (!searchInput) return;

            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    IyzicoAdmin.performSearch(this.value);
                }, 500);
            });
        },

        // Perform search
        performSearch: function(query) {
            if (query.length < 2) {
                this.showAllRows();
                return;
            }

            const rows = document.querySelectorAll('.iyzico-table tbody tr');
            rows.forEach(row => {
                const customerName = row.querySelector('.iyzico-customer-name')?.textContent || '';
                const customerEmail = row.querySelector('.iyzico-customer-email')?.textContent || '';
                
                const isMatch = customerName.toLowerCase().includes(query.toLowerCase()) ||
                               customerEmail.toLowerCase().includes(query.toLowerCase());
                
                row.style.display = isMatch ? '' : 'none';
            });
        },

        // Show all rows
        showAllRows: function() {
            const rows = document.querySelectorAll('.iyzico-table tbody tr');
            rows.forEach(row => {
                row.style.display = '';
            });
        },

        // Keyboard shortcuts setup
        setupKeyboardShortcuts: function() {
            document.addEventListener('keydown', function(e) {
                // Ctrl+R: Refresh page
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    location.reload();
                }
                
                // Ctrl+A: Select all (when not in input)
                if (e.ctrlKey && e.key === 'a' && e.target.tagName !== 'INPUT') {
                    e.preventDefault();
                    document.getElementById('cb-select-all-1').click();
                }
                
                // Escape: Close modals
                if (e.key === 'Escape') {
                    const modals = document.querySelectorAll('.modal.show');
                    modals.forEach(modal => {
                        if (typeof bootstrap !== 'undefined') {
                            bootstrap.Modal.getInstance(modal).hide();
                        }
                    });
                }
            });
        },

        // Animate statistics
        animateStats: function() {
            const statValues = document.querySelectorAll('.iyzico-stat-value');
            
            statValues.forEach(stat => {
                const finalValue = parseInt(stat.textContent.replace(/,/g, ''));
                if (isNaN(finalValue)) return;

                const duration = 2000;
                const startTime = performance.now();
                
                const animate = (currentTime) => {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    
                    const currentValue = Math.floor(progress * finalValue);
                    stat.textContent = currentValue.toLocaleString();
                    
                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    }
                };
                
                requestAnimationFrame(animate);
            });
        },

        // Update table content
        updateTableContent: function(data) {
            const tableBody = document.querySelector('.iyzico-table tbody');
            if (!tableBody) return;

            if (data.subscriptions && data.subscriptions.length > 0) {
                tableBody.innerHTML = this.renderTableRows(data.subscriptions);
            } else {
                tableBody.innerHTML = this.renderEmptyState();
            }

            this.updatePagination(data.pagination);
        },

        // Render table rows
        renderTableRows: function(subscriptions) {
            return subscriptions.map(subscription => `
                <tr data-subscription-id="${subscription.id}">
                    <td class="iyzico-column-cb">
                        <input type="checkbox" name="subscription[]" value="${subscription.id}">
                    </td>
                    <td class="iyzico-column-id">#${subscription.id}</td>
                    <td class="iyzico-column-customer">
                        <div class="iyzico-customer-info">
                            <a href="#" class="iyzico-customer-name">${subscription.customer_name}</a>
                            <span class="iyzico-customer-email">${subscription.customer_email}</span>
                        </div>
                    </td>
                    <td class="iyzico-column-product">
                        <div class="iyzico-product-info">
                            <a href="#" class="iyzico-product-name">${subscription.product_name}</a>
                            <span class="iyzico-product-sku">SKU: ${subscription.product_sku}</span>
                        </div>
                    </td>
                    <td class="iyzico-column-status">
                        <span class="iyzico-status-badge iyzico-status-${subscription.status.toLowerCase()}">
                            ${subscription.status}
                        </span>
                    </td>
                    <td class="iyzico-column-amount iyzico-text-right">
                        <span class="iyzico-amount">${subscription.amount}</span>
                    </td>
                    <td class="iyzico-column-period iyzico-text-center">
                        <span class="iyzico-period-badge">${subscription.period}</span>
                    </td>
                    <td class="iyzico-column-dates">
                        <div>Başlangıç: ${subscription.start_date}</div>
                        <div>Sonraki: ${subscription.next_payment}</div>
                    </td>
                    <td class="iyzico-column-actions">
                        <div class="iyzico-action-buttons">
                            ${this.renderActionButtons(subscription)}
                        </div>
                    </td>
                </tr>
            `).join('');
        },

        // Render action buttons
        renderActionButtons: function(subscription) {
            const buttons = [];
            
            if (subscription.status === 'active') {
                buttons.push(`
                    <button class="iyzico-btn iyzico-btn-sm iyzico-btn-warning subscription-action" 
                            data-action="suspend" data-subscription-id="${subscription.id}">
                        Askıya Al
                    </button>
                `);
            }
            
            if (subscription.status === 'suspended') {
                buttons.push(`
                    <button class="iyzico-btn iyzico-btn-sm iyzico-btn-success subscription-action" 
                            data-action="reactivate" data-subscription-id="${subscription.id}">
                        Yeniden Aktifleştir
                    </button>
                `);
            }
            
            buttons.push(`
                <button class="iyzico-btn iyzico-btn-sm iyzico-btn-danger subscription-action" 
                        data-action="cancel" data-subscription-id="${subscription.id}">
                    İptal Et
                </button>
            `);
            
            return buttons.join('');
        },

        // Render empty state
        renderEmptyState: function() {
            return `
                <tr>
                    <td colspan="9">
                        <div class="iyzico-empty-state">
                            <div class="iyzico-empty-state-icon">📭</div>
                            <h3 class="iyzico-empty-state-title">Abonelik Bulunamadı</h3>
                            <p class="iyzico-empty-state-description">
                                Seçilen kriterlere uygun abonelik bulunamadı. Filtreleri değiştirmeyi deneyin.
                            </p>
                        </div>
                    </td>
                </tr>
            `;
        },

        // Update pagination
        updatePagination: function(pagination) {
            const paginationContainer = document.querySelector('.iyzico-pagination');
            if (!paginationContainer || !pagination) return;

            paginationContainer.innerHTML = this.renderPagination(pagination);
        },

        // Render pagination
        renderPagination: function(pagination) {
            const { current_page, total_pages, total_items } = pagination;
            
            return `
                <div class="iyzico-pagination-info">
                    Toplam ${total_items} abonelik, ${total_pages} sayfa
                </div>
                <div class="iyzico-pagination-links">
                    ${this.renderPaginationLinks(current_page, total_pages)}
                </div>
            `;
        },

        // Render pagination links
        renderPaginationLinks: function(currentPage, totalPages) {
            const links = [];
            
            // Previous page
            if (currentPage > 1) {
                links.push(`
                    <a href="#" class="iyzico-pagination-link" data-page="${currentPage - 1}">
                        ← Önceki
                    </a>
                `);
            }
            
            // Page numbers
            for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                links.push(`
                    <a href="#" class="iyzico-pagination-link ${i === currentPage ? 'current' : ''}" data-page="${i}">
                        ${i}
                    </a>
                `);
            }
            
            // Next page
            if (currentPage < totalPages) {
                links.push(`
                    <a href="#" class="iyzico-pagination-link" data-page="${currentPage + 1}">
                        Sonraki →
                    </a>
                `);
            }
            
            return links.join('');
        },

        // Set loading state
        setLoadingState: function(isLoading) {
            this.state.isLoading = isLoading;
            
            const loadingElements = document.querySelectorAll('.iyzico-loading');
            loadingElements.forEach(element => {
                element.classList.toggle('iyzico-loading', isLoading);
            });
        },

        // Make AJAX request
        makeAjaxRequest: function(action, data) {
            const requestData = {
                action: action,
                nonce: this.config.nonce,
                ...data
            };

            return $.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: requestData,
                dataType: 'json'
            });
        },

        // Show notification
        showNotification: function(type, message) {
            const notification = document.createElement('div');
            notification.className = `iyzico-notification ${type}`;
            notification.innerHTML = `
                <div class="iyzico-notification-content">
                    <span class="iyzico-notification-message">${message}</span>
                    <button class="iyzico-notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
            `;

            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Check if required data is available
        if (typeof iyzicoSubscriptionAdmin !== 'undefined') {
            IyzicoAdmin.config.ajaxurl = iyzicoSubscriptionAdmin.ajaxurl;
            IyzicoAdmin.config.nonce = iyzicoSubscriptionAdmin.nonce;
            IyzicoAdmin.config.i18n = iyzicoSubscriptionAdmin.i18n || {};
            
            IyzicoAdmin.init();
        } else {
            console.error('iyzicoSubscriptionAdmin data not found');
        }
    });

})(jQuery); 