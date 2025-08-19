<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Services;

use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\ProductServiceInterface;

class ProductService implements ProductServiceInterface
{
    public function addSubscriptionProductType(array $types): array
    {
        if (did_action('init') && function_exists('__')) {
            $types['subscription'] = __('Abonelik', 'iyzico-subscription');
        } else {
            $types['subscription'] = 'Abonelik';
        }
        return $types;
    }

    public function addSubscriptionProductTab(array $tabs): array
    {
        if (did_action('init') && function_exists('__')) {
            $label = __('Abonelik Ayarları', 'iyzico-subscription');
        } else {
            $label = 'Abonelik Ayarları';
        }
        
        $tabs['subscription'] = [
            'label' => $label,
            'target' => 'subscription_product_data',
            'class' => ['show_if_subscription'],
            'priority' => 21,
        ];
        return $tabs;
    }

    public function addSubscriptionProductFields(): void
    {
        global $post;
        ?>
        <div id="subscription_product_data" class="panel woocommerce_options_panel">
            <?php
            // Fiyat alanı
            $price_label = (did_action('init') && function_exists('__')) ? 
                __('Normal Fiyat', 'woocommerce') . ' (' . get_woocommerce_currency_symbol() . ')' : 
                'Normal Fiyat (' . get_woocommerce_currency_symbol() . ')';
                
            woocommerce_wp_text_input([
                'id' => '_regular_price',
                'label' => $price_label,
                'type' => 'number',
                'custom_attributes' => [
                    'step' => 'any',
                    'min' => '0',
                ],
            ]);

            // Abonelik periyodu
            $period_label = (did_action('init') && function_exists('__')) ? 
                __('Abonelik Periyodu', 'iyzico-subscription') : 
                'Abonelik Periyodu';
                
            $period_options = [];
            if (did_action('init') && function_exists('__')) {
                $period_options = [
                    'day' => __('Günlük', 'iyzico-subscription'),
                    'week' => __('Haftalık', 'iyzico-subscription'),
                    'month' => __('Aylık', 'iyzico-subscription'),
                    'year' => __('Yıllık', 'iyzico-subscription'),
                ];
            } else {
                $period_options = [
                    'day' => 'Günlük',
                    'week' => 'Haftalık',
                    'month' => 'Aylık',
                    'year' => 'Yıllık',
                ];
            }
            
            woocommerce_wp_select([
                'id' => '_subscription_period',
                'label' => $period_label,
                'options' => $period_options,
            ]);

            // Abonelik süresi
            $length_label = (did_action('init') && function_exists('__')) ? 
                __('Abonelik Süresi', 'iyzico-subscription') : 
                'Abonelik Süresi';
                
            $length_description = (did_action('init') && function_exists('__')) ? 
                __('Abonelik süresi (0 = süresiz)', 'iyzico-subscription') : 
                'Abonelik süresi (0 = süresiz)';
                
            woocommerce_wp_text_input([
                'id' => '_subscription_length',
                'label' => $length_label,
                'description' => $length_description,
                'type' => 'number',
                'custom_attributes' => [
                    'step' => '1',
                    'min' => '0',
                ],
            ]);
            ?>
        </div>
        <?php
    }

    public function saveSubscriptionProductFields(int $post_id): void
    {
        // Ürün tipini kaydet
        update_post_meta($post_id, '_product_type', 'subscription');
        wp_set_object_terms($post_id, 'subscription', 'product_type');

        // Fiyat alanlarını kaydet
        $regular_price = isset($_POST['_regular_price']) ? wc_clean($_POST['_regular_price']) : '';
        update_post_meta($post_id, '_regular_price', $regular_price);
        update_post_meta($post_id, '_price', $regular_price);

        // Abonelik periyodunu kaydet
        $period = isset($_POST['_subscription_period']) ? wc_clean($_POST['_subscription_period']) : 'month';
        update_post_meta($post_id, '_subscription_period', $period);

        // Abonelik süresini kaydet
        $length = isset($_POST['_subscription_length']) ? wc_clean($_POST['_subscription_length']) : '';
        update_post_meta($post_id, '_subscription_length', $length);

        // Abonelik meta verilerini kaydet
        update_post_meta($post_id, '_is_subscription', 'yes');
        update_post_meta($post_id, '_subscription_price', $regular_price);

        // Ürünü yeniden yükle
        clean_post_cache($post_id);
    }

    public function hideGeneralTabForSubscription(array $tabs): array
    {
        global $post;
        if ($post && get_post_type($post) === 'product') {
            $product = wc_get_product($post->ID);
            if ($product && $product->get_type() === 'subscription') {
                unset($tabs['general']);
            }
        }
        return $tabs;
    }

    public function addSubscriptionProductJs(): void
    {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Ürün tipi değiştiğinde
                $('select#product-type').on('change', function() {
                    var type = $(this).val();
                    
                    // Subscription seçildiğinde
                    if (type === 'subscription') {
                        // Fiyat alanını göster
                        $('.options_group.pricing').show();
                        $('.options_group.subscription_pricing').show();
                        
                        // Genel sekmesini gizle
                        $('.general_options').hide();
                        $('.general_tab').hide();
                        
                        // Subscription sekmesini aktif et
                        $('.subscription_tab').addClass('active');
                        $('.subscription_options').show();
                    } else {
                        // Diğer ürün tipleri için normal davranış
                        $('.options_group.pricing').show();
                        $('.options_group.subscription_pricing').hide();
                        $('.general_options').show();
                        $('.general_tab').show();
                        $('.subscription_tab').removeClass('active');
                        $('.subscription_options').hide();
                    }
                });

                // Sayfa yüklendiğinde mevcut ürün tipini kontrol et
                if ($('select#product-type').val() === 'subscription') {
                    $('.options_group.pricing').show();
                    $('.options_group.subscription_pricing').show();
                    $('.general_options').hide();
                    $('.general_tab').hide();
                    $('.subscription_tab').addClass('active');
                    $('.subscription_options').show();
                }
            });
        </script>
        <?php
    }

    public function setSubscriptionProductClass(string $classname, string $product_type): string
    {
        if ($product_type === 'subscription') {
            return 'Iyzico\IyzipayWoocommerceSubscription\Product\WC_Product_Subscription';
        }
        return $classname;
    }

    public function setSubscriptionProductType(string $type, int $product_id): string
    {
        $product_type = get_post_meta($product_id, '_product_type', true);
        if ($product_type === 'subscription') {
            return 'subscription';
        }
        return $type;
    }
}
