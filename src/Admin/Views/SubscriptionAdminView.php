<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Admin\Views;

use Iyzico\IyzipayWoocommerceSubscription\Services\SubscriptionAdminService;

class SubscriptionAdminView {
    private SubscriptionAdminService $service;

    public function __construct(
        SubscriptionAdminService $service
    ) {
        $this->service = $service;
    }

    public function enqueue_admin_assets(): void {
        error_log('enqueue_admin_assets çağrıldı');

        // WordPress admin styles
        wp_enqueue_style('wp-admin');
        wp_enqueue_style('common');
        wp_enqueue_style('forms');
        wp_enqueue_style('dashboard');
        wp_enqueue_style('list-tables');

        // WordPress admin scripts
        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        wp_enqueue_script('postbox');

        // Components styles
        wp_enqueue_style('wp-components');
        // Dashicons for icons in Gutenberg table/actions
        wp_enqueue_style('dashicons');

        // Gutenberg/Block Editor dependencies
        $deps = [
            'wp-element',
            'wp-components',
            'wp-i18n',
        ];

        // Custom admin app (Gutenberg-based)
        $js_url = plugin_dir_url(__FILE__) . '../../../assets/js/admin-gutenberg.js';
        error_log('Admin App JS URL: ' . $js_url);

        wp_enqueue_script(
            'iyzico-subscription-admin-app',
            $js_url,
            $deps,
            '1.0.0',
            true
        );

        // Localize script
        $localize_data = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iyzico_subscription_admin'),
            'i18n' => [
                'title' => __('Abonelik Yönetimi', 'iyzico-subscription'),
                'newProduct' => __('Yeni Ürün Ekle', 'iyzico-subscription'),
                'filters' => __('Filtreler', 'iyzico-subscription'),
                'status' => __('Durum', 'iyzico-subscription'),
                'allStatuses' => __('Tüm Durumlar', 'iyzico-subscription'),
                'active' => __('Aktif', 'iyzico-subscription'),
                'suspended' => __('Askıda', 'iyzico-subscription'),
                'cancelled' => __('İptal Edildi', 'iyzico-subscription'),
                'expired' => __('Süresi Doldu', 'iyzico-subscription'),
                'searchCustomer' => __('Müşteri ara...', 'iyzico-subscription'),
                'dateFrom' => __('Başlangıç', 'iyzico-subscription'),
                'dateTo' => __('Bitiş', 'iyzico-subscription'),
                'actions' => __('İşlemler', 'iyzico-subscription'),
                'apply' => __('Uygula', 'iyzico-subscription'),
                'bulkActions' => __('Toplu İşlemler', 'iyzico-subscription'),
                'confirmAction' => __('Bu işlemi gerçekleştirmek istediğinizden emin misiniz?', 'iyzico-subscription'),
                'confirmSuspend' => __('Seçili abonelik askıya alınacak. Onaylıyor musunuz?', 'iyzico-subscription'),
                'confirmCancel' => __('Seçili abonelik iptal edilecek. Onaylıyor musunuz?', 'iyzico-subscription'),
                'confirmReactivate' => __('Seçili abonelik yeniden aktifleştirilecek. Onaylıyor musunuz?', 'iyzico-subscription'),
                'success' => __('İşlem başarıyla tamamlandı.', 'iyzico-subscription'),
                'error' => __('Bir hata oluştu. Lütfen tekrar deneyin.', 'iyzico-subscription'),
                'noItems' => __('Henüz hiç abonelik kaydı bulunmamaktadır.', 'iyzico-subscription'),
                'createFirst' => __('İlk Abonelik Ürününü Oluştur', 'iyzico-subscription'),
                'totalSubscriptions' => __('Toplam Abonelik', 'iyzico-subscription'),
                'monthlyRevenue' => __('Aylık Gelir', 'iyzico-subscription'),
                'id' => __('ID', 'iyzico-subscription'),
                'customer' => __('Müşteri', 'iyzico-subscription'),
                'product' => __('Ürün', 'iyzico-subscription'),
                'amount' => __('Tutar', 'iyzico-subscription'),
                'period' => __('Periyot', 'iyzico-subscription'),
                'nextPayment' => __('Sonraki Ödeme', 'iyzico-subscription'),
                'startDate' => __('Başlangıç', 'iyzico-subscription'),
                'view' => __('Görüntüle', 'iyzico-subscription'),
                'suspend' => __('Askıya Al', 'iyzico-subscription'),
                'cancel' => __('İptal Et', 'iyzico-subscription'),
                'reactivate' => __('Yeniden Aktifleştir', 'iyzico-subscription'),
                // Saved cards i18n
                'savedCards' => __('Kayıtlı Kartlar', 'iyzico-subscription'),
                'noSavedCards' => __('Kayıtlı kart bulunamadı.', 'iyzico-subscription'),
                'addNewCard' => __('Yeni Kart Ekle', 'iyzico-subscription'),
                'cardAlias' => __('Kart Takma Adı', 'iyzico-subscription'),
                'cardHolderName' => __('Kart Sahibi Adı', 'iyzico-subscription'),
                'cardNumber' => __('Kart Numarası', 'iyzico-subscription'),
                'expireMonth' => __('Son Kullanma Ayı (AA)', 'iyzico-subscription'),
                'expireYear' => __('Son Kullanma Yılı (YYYY)', 'iyzico-subscription'),
                'create' => __('Oluştur', 'iyzico-subscription'),
                'close' => __('Kapat', 'iyzico-subscription'),
                'fetching' => __('Yükleniyor...', 'iyzico-subscription'),
                'createdSuccess' => __('Kart başarıyla oluşturuldu.', 'iyzico-subscription'),
            ],
        ];

        error_log('Localize data: ' . print_r($localize_data, true));

        wp_localize_script('iyzico-subscription-admin-app', 'iyzicoSubscriptionAdmin', $localize_data);

        // Admin CSS
        wp_enqueue_style(
            'iyzico-subscription-admin',
            plugin_dir_url(__FILE__) . '../../../assets/css/admin.css',
            [],
            '1.0.0'
        );
    }

    public function render_subscriptions_page(): void {
        $filters = $this->get_filters_from_request();
        $data = $this->service->getSubscriptionsData($filters);

        // Prepare subscriptions for JS (augment with display fields)
        $subscriptions_for_js = array_map(function ($subscription) {
            $user = get_userdata((int) ($subscription->user_id ?? 0));
            $product = function_exists('wc_get_product') ? wc_get_product((int) ($subscription->product_id ?? 0)) : null;

            return [
                'id' => (int) ($subscription->id ?? 0),
                'customer_id' => $user ? (int) $user->ID : null,
                'customer_name' => $user ? $user->display_name : '',
                'product_id' => $product ? (int) $product->get_id() : null,
                'product_name' => $product ? $product->get_name() : '',
                'status' => (string) ($subscription->status ?? ''),
                'amount' => function_exists('wc_price') ? wc_price((float) ($subscription->amount ?? 0)) : (string) ($subscription->amount ?? ''),
                'currency' => (string) ($subscription->currency ?? 'TRY'),
                'period' => (string) ($subscription->period ?? ''),
                'start_date' => !empty($subscription->start_date) ? date_i18n('d/m/Y', strtotime($subscription->start_date)) : '',
                'next_payment' => !empty($subscription->next_payment) ? date_i18n('d/m/Y', strtotime($subscription->next_payment)) : '',
            ];
        }, $data['subscriptions']);

        // Enhance stats
        $stats = $data['stats'];
        if (is_object($stats)) {
            $stats->monthly_revenue_formatted = function_exists('wc_price') ? wc_price((float) ($stats->monthly_revenue ?? 0)) : (string) ($stats->monthly_revenue ?? '');
        }

        $initial_state = [
            'filters' => $filters,
            'stats' => $stats,
            'subscriptions' => $subscriptions_for_js,
            'links' => [
                'newProduct' => admin_url('post-new.php?post_type=product'),
            ],
        ];

        ?>
        <div class="wrap">
            <div id="iyzico-subscription-app"
                 data-initial-state='<?php echo esc_attr(wp_json_encode($initial_state)); ?>'></div>
            <noscript><?php esc_html_e('Bu sayfayı görüntülemek için JavaScript etkin olmalıdır.', 'iyzico-subscription'); ?></noscript>
        </div>
        <?php
    }

    private function get_filters_from_request(): array {
        return [
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'customer_search' => isset($_GET['customer_search']) ? sanitize_text_field($_GET['customer_search']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
        ];
    }
}
