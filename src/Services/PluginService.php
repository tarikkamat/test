<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Services;

use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\PluginServiceInterface;
use Iyzico\IyzipayWoocommerceSubscription\Migrations\MigrationManager;

class PluginService implements PluginServiceInterface
{
    public function loadPluginTextdomain(): void
    {
        load_plugin_textdomain('iyzico-subscription', false, dirname(dirname(plugin_basename(__FILE__))) . '/languages');
    }

    public function createDatabaseTables(): void
    {
        // Migration sistemini kullan
        $migration_manager = new MigrationManager();
        $migration_manager->run_migrations();
    }

    public function addIyzicoGateway(array $gateways): array
    {
        $gateways[] = 'Iyzico\IyzipayWoocommerceSubscription\Gateway\IyzicoGateway';
        return $gateways;
    }

    public function addWooCommerceBlocksSupport(): void
    {
        static $blocksSupportHookAdded = false;
        if ($blocksSupportHookAdded) {
            return;
        }
        $blocksSupportHookAdded = true;

        // Always register to the payment method type registration hook; Blocks will call this when ready
        add_action('woocommerce_blocks_payment_method_type_registration', function ($payment_method_registry) {
            if (! class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
                return;
            }
            if (! class_exists('Iyzico\\IyzipayWoocommerceSubscription\\Gateway\\IyzicoBlocksSupport')) {
                require_once plugin_dir_path(__FILE__) . '../Gateway/IyzicoBlocksSupport.php';
            }
            if ($payment_method_registry instanceof \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry) {
                $payment_method_registry->register(new \Iyzico\IyzipayWoocommerceSubscription\Gateway\IyzicoBlocksSupport());
            }
        });
    }
}
