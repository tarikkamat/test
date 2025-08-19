<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Product;

class WC_Product_Subscription extends \WC_Product {
    public function __construct($product) {
        $this->data['type'] = 'subscription';
        parent::__construct($product);
    }

    public function get_type() {
        return 'subscription';
    }

    public function is_type($type) {
        return $type === 'subscription' || parent::is_type($type);
    }

    public function is_purchasable() {
        return true;
    }

    public function is_virtual() {
        return true;
    }

    public function is_downloadable() {
        return false;
    }

    public function needs_shipping() {
        return false;
    }

    public function single_add_to_cart_text() {
        if (!function_exists('__') || !did_action('init')) {
            return 'Abonelik Başlat';
        }
        return __('Abonelik Başlat', 'iyzico-subscription');
    }

    public function add_to_cart_text() {
        if (!function_exists('__') || !did_action('init')) {
            return 'Abonelik Başlat';
        }
        return __('Abonelik Başlat', 'iyzico-subscription');
    }

    public function get_subscription_period() {
        return get_post_meta($this->get_id(), '_subscription_period', true);
    }

    public function get_subscription_length() {
        return get_post_meta($this->get_id(), '_subscription_length', true);
    }

    public function get_subscription_price() {
        return get_post_meta($this->get_id(), '_subscription_price', true);
    }

    public function get_price_html($deprecated = '') {
        $price = $this->get_price();
        $period = $this->get_subscription_period();
        $length = $this->get_subscription_length();

        if ($price === '') {
            return '';
        }

        $period_text = '';
        if (!function_exists('__') || !did_action('init')) {
            // Fallback metinler
            switch ($period) {
                case 'day':
                    $period_text = 'günlük';
                    break;
                case 'week':
                    $period_text = 'haftalık';
                    break;
                case 'month':
                    $period_text = 'aylık';
                    break;
                case 'year':
                    $period_text = 'yıllık';
                    break;
            }
        } else {
            switch ($period) {
                case 'day':
                    $period_text = __('günlük', 'iyzico-subscription');
                    break;
                case 'week':
                    $period_text = __('haftalık', 'iyzico-subscription');
                    break;
                case 'month':
                    $period_text = __('aylık', 'iyzico-subscription');
                    break;
                case 'year':
                    $period_text = __('yıllık', 'iyzico-subscription');
                    break;
            }
        }

        $length_text = '';
        if ($length > 0) {
            if (!function_exists('__') || !did_action('init')) {
                $length_text = sprintf(' (%d %s)', $length, $period_text);
            } else {
                $length_text = sprintf(__(' (%d %s)', 'iyzico-subscription'), $length, $period_text);
            }
        } else {
            if (!function_exists('__') || !did_action('init')) {
                $length_text = sprintf(' (Süresiz %s)', $period_text);
            } else {
                $length_text = sprintf(__(' (Süresiz %s)', 'iyzico-subscription'), $period_text);
            }
        }

        return wc_price($price) . $length_text;
    }
} 