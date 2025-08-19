<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Services;

use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\TemplateServiceInterface;

class TemplateService implements TemplateServiceInterface
{
    private string $template_path;

    public function __construct() {
        $this->template_path = plugin_dir_path(__FILE__) . '../templates/emails/';
    }

    public function loadTemplate(string $template_name, array $data): string
    {
        $template_file = $this->template_path . $template_name . '.php';
        
        if (file_exists($template_file)) {
            ob_start();
            extract($data);
            include $template_file;
            return ob_get_clean();
        }
        
        return $this->getFallbackTemplate($template_name, $data);
    }

    public function getFallbackTemplate(string $template_name, array $data): string
    {
        switch ($template_name) {
            case 'subscription-created':
                /* translators: 1: user name, 2: product name, 3: amount, 4: period, 5: start date, 6: next payment date */
                return sprintf(
                    __('Merhaba %1$s,\n\n%2$s aboneliğiniz başarıyla oluşturuldu.\n\nTutar: %3$s\nPeriyot: %4$s\nBaşlangıç: %5$s\nSonraki Ödeme: %6$s\n\nTeşekkürler!', 'iyzico-subscription'),
                    $data['user_name'],
                    $data['product_name'],
                    $data['amount'],
                    $data['period'],
                    $data['start_date'],
                    $data['next_payment']
                );
                
            case 'renewal-success':
                /* translators: 1: user name, 2: product name, 3: amount, 4: payment date, 5: next payment */
                return sprintf(
                    __('Merhaba %1$s,\n\n%2$s aboneliğiniz başarıyla yenilendi.\n\nTutar: %3$s\nÖdeme Tarihi: %4$s\nSonraki Ödeme: %5$s\n\nTeşekkürler!', 'iyzico-subscription'),
                    $data['user_name'],
                    $data['product_name'],
                    $data['amount'],
                    $data['payment_date'],
                    $data['next_payment']
                );
                
            case 'renewal-failed':
                /* translators: 1: user name, 2: product name, 3: error message, 4: retry date, 5: account url */
                return sprintf(
                    __('Merhaba %1$s,\n\n%2$s aboneliğinizin ödemesi başarısız oldu.\n\nHata: %3$s\nYeniden deneme: %4$s\n\nLütfen ödeme bilgilerinizi kontrol edin: %5$s\n\nTeşekkürler!', 'iyzico-subscription'),
                    $data['user_name'],
                    $data['product_name'],
                    $data['error_message'],
                    $data['retry_date'],
                    $data['account_url']
                );
                
            case 'subscription-cancelled':
                /* translators: 1: user name, 2: product name, 3: cancellation date */
                return sprintf(
                    __('Merhaba %1$s,\n\n%2$s aboneliğiniz iptal edildi.\n\nİptal Tarihi: %3$s\n\nBizi tercih ettiğiniz için teşekkürler!', 'iyzico-subscription'),
                    $data['user_name'],
                    $data['product_name'],
                    $data['cancellation_date']
                );
                
            case 'subscription-suspended':
                /* translators: 1: user name, 2: product name, 3: failed payments count, 4: suspension date, 5: account url */
                return sprintf(
                    __('Merhaba %1$s,\n\n%2$s aboneliğiniz askıya alındı.\n\nSebep: %3$d başarısız ödeme\nTarih: %4$s\n\nHesabınızdan yeniden aktifleştirebilirsiniz: %5$s', 'iyzico-subscription'),
                    $data['user_name'],
                    $data['product_name'],
                    $data['failed_payments'],
                    $data['suspension_date'],
                    $data['account_url']
                );
                
            case 'subscription-expiring':
                /* translators: 1: user name, 2: product name, 3: expiry date, 4: days remaining, 5: renewal url */
                return sprintf(
                    __('Merhaba %1$s,\n\n%2$s aboneliğiniz yakında sona eriyor.\n\nBitiş Tarihi: %3$s\nKalan Gün: %4$d\n\nYenilemek için: %5$s', 'iyzico-subscription'),
                    $data['user_name'],
                    $data['product_name'],
                    $data['expiry_date'],
                    $data['days_remaining'],
                    $data['renewal_url']
                );
        }
        
        return '';
    }

    public function getPeriodLabel(string $period): string
    {
        $labels = [
            'day' => __('Günlük', 'iyzico-subscription'),
            'week' => __('Haftalık', 'iyzico-subscription'),
            'month' => __('Aylık', 'iyzico-subscription'),
            'year' => __('Yıllık', 'iyzico-subscription'),
        ];
        
        return $labels[$period] ?? $period;
    }

    public function getDaysUntilExpiry(string $end_date): int
    {
        $now = new \DateTime();
        $expiry = new \DateTime($end_date);
        $diff = $now->diff($expiry);
        
        return $diff->days;
    }

    public function loadSubscriptionTemplate(string $template, string $template_name, array $args, string $template_path, string $default_path): string
    {
        global $product;
        
        // $product kontrolü - sadece WC_Product objesi ise devam et
        if (!is_object($product) || !method_exists($product, 'get_type') || $product->get_type() !== 'subscription') {
            return $template;
        }

        if ($template_name === 'single-product/add-to-cart/simple.php') {
            $custom_template = plugin_dir_path(dirname(__FILE__)) . '../templates/single-product/add-to-cart/subscription.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    public function locateSubscriptionTemplate(string $template, string $template_name, string $template_path): string
    {
        global $product;
        
        // $product kontrolü - sadece WC_Product objesi ise devam et
        if (!is_object($product) || !method_exists($product, 'get_type') || $product->get_type() !== 'subscription') {
            return $template;
        }

        if ($template_name === 'single-product/add-to-cart/simple.php') {
            $custom_template = plugin_dir_path(dirname(__FILE__)) . '../templates/single-product/add-to-cart/subscription.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    public function displaySubscriptionAddToCart(): void
    {
        global $product;
        
        // $product kontrolü - sadece WC_Product objesi ise devam et
        if (!is_object($product) || !method_exists($product, 'get_type') || $product->get_type() !== 'subscription') {
            return;
        }
        
        // Normal add to cart butonunu gizle
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        
        // Subscription template'ini yükle
        $template_path = plugin_dir_path(dirname(__FILE__)) . '../templates/single-product/add-to-cart/subscription.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }
}
