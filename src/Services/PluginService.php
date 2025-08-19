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
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once plugin_dir_path(__FILE__) . '../Gateway/IyzicoBlocksSupport.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function(\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new \Iyzico\IyzipayWoocommerceSubscription\Gateway\IyzicoBlocksSupport());
                }
            );
        }
    }
}
