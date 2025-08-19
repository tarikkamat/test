<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Services;

use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\EmailServiceInterface;
use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\TemplateServiceInterface;

class EmailService implements EmailServiceInterface
{
    private TemplateServiceInterface $templateService;

    public function __construct(TemplateServiceInterface $templateService) {
        $this->templateService = $templateService;
        
        // E-posta hook'ları
        add_action('iyzico_subscription_created', [$this, 'sendSubscriptionCreatedEmail']);
        add_action('iyzico_subscription_renewal_success', [$this, 'sendRenewalSuccessEmail']);
        add_action('iyzico_subscription_renewal_failed', [$this, 'sendRenewalFailedEmail'], 10, 2);
        add_action('iyzico_subscription_cancelled', [$this, 'sendCancellationEmail']);
        add_action('iyzico_subscription_suspended', [$this, 'sendSuspensionEmail']);
        add_action('iyzico_subscription_expiring', [$this, 'sendExpiringEmail']);
    }

    public function sendSubscriptionCreatedEmail(object $subscription): void {
        if (!$this->isEmailEnabled()) {
            return;
        }

        $user = get_user_by('id', $subscription->user_id);
        $product = get_post($subscription->product_id);
        
        $subject = sprintf(__('Aboneliğiniz Oluşturuldu - %s', 'iyzico-subscription'), $product->post_title);
        
        $template_data = [
            'user_name' => $user->display_name,
            'product_name' => $product->post_title,
            'amount' => wc_price($subscription->amount),
            'period' => $this->templateService->getPeriodLabel($subscription->period),
            'start_date' => date('d.m.Y', strtotime($subscription->start_date)),
            'next_payment' => date('d.m.Y', strtotime($subscription->next_payment)),
            'subscription_id' => $subscription->id,
        ];
        
        $message = $this->templateService->loadTemplate('subscription-created', $template_data);
        
        $this->sendEmail($user->user_email, $subject, $message);
        
        // Admin'e de bildir
        $this->sendAdminNotification('new_subscription', $subscription);
    }

    public function sendRenewalSuccessEmail(object $subscription): void {
        if (!$this->isEmailEnabled()) {
            return;
        }

        $user = get_user_by('id', $subscription->user_id);
        $product = get_post($subscription->product_id);
        
        $subject = sprintf(__('Aboneliğiniz Yenilendi - %s', 'iyzico-subscription'), $product->post_title);
        
        $template_data = [
            'user_name' => $user->display_name,
            'product_name' => $product->post_title,
            'amount' => wc_price($subscription->amount),
            'payment_date' => date('d.m.Y'),
            'next_payment' => date('d.m.Y', strtotime($subscription->next_payment)),
            'subscription_id' => $subscription->id,
        ];
        
        $message = $this->templateService->loadTemplate('renewal-success', $template_data);
        
        $this->sendEmail($user->user_email, $subject, $message);
    }

    public function sendRenewalFailedEmail(object $subscription, string $error): void {
        if (!$this->isEmailEnabled()) {
            return;
        }

        $user = get_user_by('id', $subscription->user_id);
        $product = get_post($subscription->product_id);
        
        $subject = sprintf(__('Abonelik Ödeme Hatası - %s', 'iyzico-subscription'), $product->post_title);
        
        $template_data = [
            'user_name' => $user->display_name,
            'product_name' => $product->post_title,
            'amount' => wc_price($subscription->amount),
            'error_message' => $error,
            'retry_date' => date('d.m.Y', strtotime('+3 days')),
            'subscription_id' => $subscription->id,
            'account_url' => wc_get_account_endpoint_url('subscriptions'),
        ];
        
        $message = $this->templateService->loadTemplate('renewal-failed', $template_data);
        
        $this->sendEmail($user->user_email, $subject, $message);
        
        // Admin'e de bildir
        $this->sendAdminNotification('payment_failed', $subscription, $error);
    }

    public function sendCancellationEmail(object $subscription): void {
        if (!$this->isEmailEnabled()) {
            return;
        }

        $user = get_user_by('id', $subscription->user_id);
        $product = get_post($subscription->product_id);
        
        $subject = sprintf(__('Aboneliğiniz İptal Edildi - %s', 'iyzico-subscription'), $product->post_title);
        
        $template_data = [
            'user_name' => $user->display_name,
            'product_name' => $product->post_title,
            'cancellation_date' => date('d.m.Y'),
            'subscription_id' => $subscription->id,
        ];
        
        $message = $this->templateService->loadTemplate('subscription-cancelled', $template_data);
        
        $this->sendEmail($user->user_email, $subject, $message);
    }

    public function sendSuspensionEmail(object $subscription): void {
        if (!$this->isEmailEnabled()) {
            return;
        }

        $user = get_user_by('id', $subscription->user_id);
        $product = get_post($subscription->product_id);
        
        $subject = sprintf(__('Aboneliğiniz Askıya Alındı - %s', 'iyzico-subscription'), $product->post_title);
        
        $template_data = [
            'user_name' => $user->display_name,
            'product_name' => $product->post_title,
            'suspension_date' => date('d.m.Y'),
            'failed_payments' => $subscription->failed_payments,
            'subscription_id' => $subscription->id,
            'account_url' => wc_get_account_endpoint_url('subscriptions'),
        ];
        
        $message = $this->templateService->loadTemplate('subscription-suspended', $template_data);
        
        $this->sendEmail($user->user_email, $subject, $message);
    }

    public function sendExpiringEmail(object $subscription): void {
        if (!$this->isEmailEnabled()) {
            return;
        }

        $user = get_user_by('id', $subscription->user_id);
        $product = get_post($subscription->product_id);
        
        $subject = sprintf(__('Aboneliğiniz Yakında Sona Eriyor - %s', 'iyzico-subscription'), $product->post_title);
        
        $template_data = [
            'user_name' => $user->display_name,
            'product_name' => $product->post_title,
            'expiry_date' => date('d.m.Y', strtotime($subscription->end_date)),
            'days_remaining' => $this->templateService->getDaysUntilExpiry($subscription->end_date),
            'subscription_id' => $subscription->id,
            'renewal_url' => wc_get_account_endpoint_url('subscriptions'),
        ];
        
        $message = $this->templateService->loadTemplate('subscription-expiring', $template_data);
        
        $this->sendEmail($user->user_email, $subject, $message);
    }

    public function sendAdminNotification(string $type, object $subscription, ?string $extra_data = null): void {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        switch ($type) {
            case 'new_subscription':
                $subject = sprintf(__('[%s] Yeni Abonelik Oluşturuldu', 'iyzico-subscription'), $site_name);
                $message = sprintf(
                    __('Yeni bir abonelik oluşturuldu:\n\nAbonelik ID: %d\nMüşteri: %s\nÜrün: %s\nTutar: %s\n\nYönetim panelinden detayları görüntüleyebilirsiniz.', 'iyzico-subscription'),
                    $subscription->id,
                    get_user_by('id', $subscription->user_id)->display_name,
                    get_the_title($subscription->product_id),
                    wc_price($subscription->amount)
                );
                break;
                
            case 'payment_failed':
                $subject = sprintf(__('[%s] Abonelik Ödeme Hatası', 'iyzico-subscription'), $site_name);
                $message = sprintf(
                    __('Abonelik ödemesi başarısız oldu:\n\nAbonelik ID: %d\nMüşteri: %s\nÜrün: %s\nHata: %s\n\nLütfen kontrol edin.', 'iyzico-subscription'),
                    $subscription->id,
                    get_user_by('id', $subscription->user_id)->display_name,
                    get_the_title($subscription->product_id),
                    $extra_data
                );
                break;
        }
        
        wp_mail($admin_email, $subject, $message);
    }

    public function checkExpiringSubscriptions(): void {
        global $wpdb;
        
        $expiring_subscriptions = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}iyzico_subscriptions 
             WHERE status = 'active' 
             AND end_date IS NOT NULL 
             AND end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
             AND id NOT IN (
                 SELECT subscription_id FROM {$wpdb->prefix}iyzico_subscription_notifications 
                 WHERE notification_type = 'expiring' 
                 AND sent_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             )"
        );
        
        foreach ($expiring_subscriptions as $subscription) {
            $this->sendExpiringEmail($subscription);
            
            // Bildirim gönderildi olarak işaretle
            $wpdb->insert(
                $wpdb->prefix . 'iyzico_subscription_notifications',
                [
                    'subscription_id' => $subscription->id,
                    'notification_type' => 'expiring',
                    'sent_at' => current_time('mysql')
                ]
            );
        }
    }

    private function sendEmail(string $to, string $subject, string $message): void {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        wp_mail($to, $subject, $message, $headers);
    }

    private function isEmailEnabled(): bool {
        return get_option('iyzico_subscription_email_notifications', 1) == 1;
    }
} 