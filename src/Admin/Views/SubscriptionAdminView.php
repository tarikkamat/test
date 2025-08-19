<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Admin\Views;

use Iyzico\IyzipayWoocommerceSubscription\Services\SubscriptionAdminService;

class SubscriptionAdminView {
    private SubscriptionAdminService $service;
    private SubscriptionTableRenderer $tableRenderer;
    private SubscriptionStatsRenderer $statsRenderer;
    private SubscriptionFiltersRenderer $filtersRenderer;

    public function __construct(
        SubscriptionAdminService $service,
        SubscriptionTableRenderer $tableRenderer,
        SubscriptionStatsRenderer $statsRenderer,
        SubscriptionFiltersRenderer $filtersRenderer
    ) {
        $this->service = $service;
        $this->tableRenderer = $tableRenderer;
        $this->statsRenderer = $statsRenderer;
        $this->filtersRenderer = $filtersRenderer;
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
        
        // Bootstrap CSS ve JS
        wp_enqueue_style(
            'bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
            [],
            '5.3.0'
        );
        
        wp_enqueue_script(
            'bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
            ['jquery'],
            '5.3.0',
            true
        );
        
        // Custom admin script
        $js_url = plugin_dir_url(__FILE__) . '../../../assets/js/admin.js';
        error_log('Admin JS URL: ' . $js_url);
        
        wp_enqueue_script(
            'iyzico-subscription-admin',
            $js_url,
            ['jquery', 'bootstrap'],
            '1.0.0',
            true
        );

        // Localize script
        $localize_data = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iyzico_subscription_admin'),
            'i18n' => [
                'confirmAction' => __('Bu işlemi gerçekleştirmek istediğinizden emin misiniz?', 'iyzico-subscription'),
                'success' => __('İşlem başarıyla tamamlandı.', 'iyzico-subscription'),
                'error' => __('Bir hata oluştu. Lütfen tekrar deneyin.', 'iyzico-subscription'),
            ],
        ];
        
        error_log('Localize data: ' . print_r($localize_data, true));
        
        wp_localize_script('iyzico-subscription-admin', 'iyzicoSubscriptionAdmin', $localize_data);

        // Admin CSS
        wp_enqueue_style(
            'iyzico-subscription-admin',
            plugin_dir_url(__FILE__) . '../../../assets/css/admin.css',
            ['bootstrap'],
            '1.0.0'
        );
    }

    public function render_subscriptions_page(): void {
        $filters = $this->get_filters_from_request();
        $data = $this->service->getSubscriptionsData($filters);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php _e('Abonelik Yönetimi', 'iyzico-subscription'); ?>
                <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="page-title-action">
                    <?php _e('Yeni Ürün Ekle', 'iyzico-subscription'); ?>
                </a>
            </h1>
            <hr class="wp-header-end">

            <?php $this->statsRenderer->render($data['stats']); ?>
            <?php $this->filtersRenderer->render($filters); ?>
            <?php $this->tableRenderer->render($data['subscriptions']); ?>
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
