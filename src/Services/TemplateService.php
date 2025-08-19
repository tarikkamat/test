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
                return sprintf(
                    __('Merhaba %s,\n\n%s aboneliğiniz başarıyla oluşturuldu.\n\nTutar: %s\nPeriyot: %s\nBaşlangıç: %s\nSonraki Ödeme: %s\n\nTeşekkürler!', 'iyzico-subscription'),
                    $data['user_name'],
                    $data['product_name'],
                    $data['amount'],
                    $data['period'],
                    $data['start_date'],
                    $data['next_payment']
                );
                
            case 'renewal-success':
                return sprintf(
                    __('Merhaba %s,\n\n%s aboneliğiniz başarıyla yenilendi.\n\nTutar: %s\nÖdeme Tarihi: %s\nSonraki Ödeme: %s\n\nTeşekkürler!', 'iyzico-subscription'),
                    $data['user_name'],
                    $data['product_name'],
                    $data['amount'],
                    $data['payment_date'],
                    $data['next_payment']
                );
                
            case 'renewal-failed':
                return sprintf(
                    __('Merhaba %s,\n\n%s aboneliğinizin ödemesi başarısız oldu.\n\nHata: %s\nYeniden deneme: %s\n\nLütfen ödeme bilgilerinizi kontrol edin: %s\n\nTeşekkürler!', 'iyzico-subscription'),
                    $data['user_name'],
                    $data['product_name'],
                    $data['error_message'],
                    $data['retry_date'],
                    $data['account_url']
                );
                
            case 'subscription-cancelled':
                return sprintf(
                    __('Merhaba %s,\n\n%s aboneliğiniz iptal edildi.\n\nİptal Tarihi: %s\n\nBizi tercih ettiğiniz için teşekkürler!', 'iyzico-subscription'),
                    $data['user_name'],
                    $data['product_name'],
                    $data['cancellation_date']
                );
                
            case 'subscription-suspended':
                return sprintf(
                    __('Merhaba %s,\n\n%s aboneliğiniz askıya alındı.\n\nSebep: %d başarısız ödeme\nTarih: %s\n\nHesabınızdan yeniden aktifleştirebilirsiniz: %s', 'iyzico-subscription'),
                    $data['user_name'],
                    $data['product_name'],
                    $data['failed_payments'],
                    $data['suspension_date'],
                    $data['account_url']
                );
                
            case 'subscription-expiring':
                return sprintf(
                    __('Merhaba %s,\n\n%s aboneliğiniz yakında sona eriyor.\n\nBitiş Tarihi: %s\nKalan Gün: %d\n\nYenilemek için: %s', 'iyzico-subscription'),
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
