<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Services;

use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\HookServiceInterface;
use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\ProductServiceInterface;
use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\TemplateServiceInterface;
use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\AccountServiceInterface;
use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\PluginServiceInterface;
use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\RenewalServiceInterface;

class HookService implements HookServiceInterface
{
    private ProductServiceInterface $productService;
    private TemplateServiceInterface $templateService;
    private AccountServiceInterface $accountService;
    private PluginServiceInterface $pluginService;
    private RenewalServiceInterface $renewalService;

    public function __construct(
        ProductServiceInterface $productService,
        TemplateServiceInterface $templateService,
        AccountServiceInterface $accountService,
        PluginServiceInterface $pluginService,
        RenewalServiceInterface $renewalService
    ) {
        $this->productService = $productService;
        $this->templateService = $templateService;
        $this->accountService = $accountService;
        $this->pluginService = $pluginService;
        $this->renewalService = $renewalService;
    }

    public function registerProductHooks(): void
    {
        // Ürün tipi ve alanları
        add_filter('product_type_selector', [$this->productService, 'addSubscriptionProductType']);
        add_filter('woocommerce_product_data_tabs', [$this->productService, 'addSubscriptionProductTab']);
        add_action('woocommerce_product_data_panels', [$this->productService, 'addSubscriptionProductFields']);
        add_action('woocommerce_process_product_meta', [$this->productService, 'saveSubscriptionProductFields']);
        add_filter('woocommerce_product_data_tabs', [$this->productService, 'hideGeneralTabForSubscription']);
        add_action('admin_footer', [$this->productService, 'addSubscriptionProductJs']);
        
        // Ürün tipi filtreleri
        add_filter('woocommerce_product_class', [$this->productService, 'setSubscriptionProductClass'], 10, 2);
        add_filter('woocommerce_product_type_query', [$this->productService, 'setSubscriptionProductType'], 10, 2);
    }

    public function registerTemplateHooks(): void
    {
        // Template yükleme
        add_filter('wc_get_template', [$this->templateService, 'loadSubscriptionTemplate'], 10, 5);
        add_filter('woocommerce_locate_template', [$this->templateService, 'locateSubscriptionTemplate'], 10, 3);
        add_filter('woocommerce_locate_core_template', [$this->templateService, 'locateSubscriptionTemplate'], 10, 3);
        
        // Subscription buton hook'u
        add_action('woocommerce_single_product_summary', [$this->templateService, 'displaySubscriptionAddToCart'], 30);
    }

    public function registerAccountHooks(): void
    {
        // Müşteri hesap sayfası
        add_action('init', [$this->accountService, 'addAccountEndpoints']);
        add_filter('woocommerce_account_menu_items', [$this->accountService, 'addAccountMenuItems']);
        add_action('woocommerce_account_subscriptions_endpoint', [$this->accountService, 'renderSubscriptionsAccountPage']);
    }

    public function registerPaymentHooks(): void
    {
        // Ödeme işlemleri
        add_filter('woocommerce_payment_gateways', [$this->pluginService, 'addIyzicoGateway']);
        
        // WooCommerce Blocks desteği
        add_action('woocommerce_blocks_loaded', [$this->pluginService, 'addWooCommerceBlocksSupport']);
    }

    public function registerAdminHooks(): void
    {
        // Admin paneli
        if (is_admin()) {
            \Iyzico\IyzipayWoocommerceSubscription\Admin\AdminContainer::get_subscription_admin_controller();
        }
    }

    public function registerAjaxHooks(): void
    {
        // AJAX işlemleri
        add_action('wp_ajax_iyzico_subscription_action', [$this, 'handleSubscriptionAction']);
        add_action('wp_ajax_nopriv_iyzico_subscription_action', [$this, 'handleSubscriptionAction']);
    }

    public function handleSubscriptionAction(): void
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iyzico_subscription_action')) {
            wp_die('Güvenlik kontrolü başarısız.');
        }

        $subscription_id = intval($_POST['subscription_id']);
        $action = sanitize_text_field($_POST['subscription_action']);
        
        $result = false;
        
        switch ($action) {
            case 'suspend':
                $result = $this->renewalService->suspendSubscription($subscription_id);
                break;
            case 'cancel':
                $result = $this->renewalService->cancelSubscription($subscription_id);
                break;
            case 'reactivate':
                $result = $this->renewalService->reactivateSubscription($subscription_id);
                break;
        }
        
        if ($result) {
            wp_send_json_success(['message' => __('İşlem başarıyla tamamlandı.', 'iyzico-subscription')]);
        } else {
            wp_send_json_error(['message' => __('İşlem başarısız oldu.', 'iyzico-subscription')]);
        }
    }
}
