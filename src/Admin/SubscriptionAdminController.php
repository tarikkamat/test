<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Admin;

use Iyzico\IyzipayWoocommerceSubscription\Admin\Views\SubscriptionAdminView;
use Iyzico\IyzipayWoocommerceSubscription\Services\SubscriptionAdminService;
use Iyzico\IyzipayWoocommerceSubscription\Services\RenewalService;

class SubscriptionAdminController {
    private SubscriptionAdminView $view;
    private SubscriptionAdminService $service;
    private RenewalService $renewalService;

    public function __construct(
        SubscriptionAdminView $view,
        SubscriptionAdminService $service,
        RenewalService $renewalService
    ) {
        $this->view = $view;
        $this->service = $service;
        $this->renewalService = $renewalService;
        
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_iyzico_subscription_admin_action', [$this, 'handle_admin_action']);
        add_action('wp_ajax_iyzico_trigger_payment', [$this, 'handle_trigger_payment']);
    }

    public function add_menu_page(): void {
        $menu_title = (did_action('init') && function_exists('__')) ? 
            __('Abonelikler', 'iyzico-subscription') : 
            'Abonelikler';
            
        add_submenu_page(
            'woocommerce',
            $menu_title,
            $menu_title,
            'manage_woocommerce',
            'iyzico-subscriptions',
            [$this->view, 'render_subscriptions_page']
        );
    }

    public function enqueue_admin_scripts(string $hook): void {
        error_log('enqueue_admin_scripts çağrıldı. Hook: ' . $hook);
        
        if ('woocommerce_page_iyzico-subscriptions' !== $hook) {
            error_log('Hook eşleşmedi. Beklenen: woocommerce_page_iyzico-subscriptions, Gelen: ' . $hook);
            return;
        }

        error_log('Hook eşleşti, admin assets yükleniyor...');
        $this->view->enqueue_admin_assets();
    }

    public function handle_admin_action(): void {
        // Debug log ekle
        error_log('iyzico_subscription_admin_action çağrıldı');
        error_log('POST verileri: ' . print_r($_POST, true));
        
        check_ajax_referer('iyzico_subscription_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            error_log('Kullanıcı yetkisi yok');
            wp_send_json_error(['message' => __('Yetkiniz yok.', 'iyzico-subscription')]);
        }

        $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
        $action = isset($_POST['subscription_action']) ? sanitize_text_field($_POST['subscription_action']) : '';

        error_log("Subscription ID: $subscription_id, Action: $action");

        if (!$subscription_id || !$action) {
            error_log('Geçersiz istek parametreleri');
            wp_send_json_error(['message' => __('Geçersiz istek.', 'iyzico-subscription')]);
        }

        $result = $this->service->performAction($subscription_id, $action);
        error_log("performAction sonucu: " . ($result ? 'true' : 'false'));

        if ($result) {
            wp_send_json_success(['message' => __('İşlem başarıyla tamamlandı.', 'iyzico-subscription')]);
        } else {
            wp_send_json_error(['message' => __('İşlem başarısız oldu.', 'iyzico-subscription')]);
        }
    }

    public function handle_trigger_payment(): void {
        check_ajax_referer('iyzico_trigger_payment', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Yetkiniz yok.', 'iyzico-subscription')]);
        }

        $subscription_id = intval($_POST['subscription_id']);
        
        $subscription = $this->service->getSubscriptionById($subscription_id);
        if (!$subscription) {
            wp_send_json_error(['message' => __('Abonelik bulunamadı.', 'iyzico-subscription')]);
        }
        
        $result = $this->renewalService->processSingleRenewal($subscription);

        if ($result) {
            wp_send_json_success(['message' => __('Ödeme başarıyla alındı.', 'iyzico-subscription')]);
        } else {
            wp_send_json_error(['message' => __('Ödeme alınamadı.', 'iyzico-subscription')]);
        }
    }
}
