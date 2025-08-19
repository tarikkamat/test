<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Gateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * iyzico payment method integration for WooCommerce Blocks
 */
final class IyzicoBlocksSupport extends AbstractPaymentMethodType {

    /**
     * The gateway instance.
     *
     * @var IyzicoGateway
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'iyzico_subscription';

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_iyzico_subscription_settings', []);
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[$this->name];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $script_asset_path = plugin_dir_path(__FILE__) . '../assets/js/frontend/blocks.asset.php';
        $script_asset      = file_exists($script_asset_path)
            ? require($script_asset_path)
            : array(
                'dependencies' => array(),
                'version'      => '1.0.0'
            );
        $script_url        = plugin_dir_url(__FILE__) . '../assets/js/frontend/blocks.js';

        wp_register_script(
            'wc-iyzico-payments-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wc-iyzico-payments-blocks', 'iyzico-subscription', plugin_dir_path(__FILE__) . '../languages');
        }

        return ['wc-iyzico-payments-blocks'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports'    => array_filter($this->gateway->supports, [$this->gateway, 'supports'])
        ];
    }
} 