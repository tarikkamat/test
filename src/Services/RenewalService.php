<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Services;

use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\RenewalServiceInterface;
use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\EmailServiceInterface;
use Iyzico\IyzipayWoocommerceSubscription\Models\Interfaces\SubscriptionRepositoryInterface;
use Iyzico\IyzipayWoocommerceSubscription\Gateway\IyzicoGateway;

class RenewalService implements RenewalServiceInterface
{
    private SubscriptionRepositoryInterface $subscriptionRepository;
    private IyzicoGateway $gateway;
    private EmailServiceInterface $emailService;

    public function __construct(
        SubscriptionRepositoryInterface $subscriptionRepository,
        IyzicoGateway $gateway,
        EmailServiceInterface $emailService
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->gateway = $gateway;
        $this->emailService = $emailService;
        
        // Cron job'ları kaydet
        add_action('wp', [$this, 'scheduleRenewalCheck']);
        add_action('iyzico_subscription_renewal_check', [$this, 'processRenewals']);
        add_action('iyzico_subscription_retry_failed', [$this, 'retryFailedPayments']);
    }

    public function scheduleRenewalCheck(): void {
        if (!wp_next_scheduled('iyzico_subscription_renewal_check')) {
            wp_schedule_event(time(), 'hourly', 'iyzico_subscription_renewal_check');
        }
        
        if (!wp_next_scheduled('iyzico_subscription_retry_failed')) {
            wp_schedule_event(time(), 'daily', 'iyzico_subscription_retry_failed');
        }
    }

    public function processRenewals(): void {
        $due_subscriptions = $this->subscriptionRepository->findDueRenewals();
        
        foreach ($due_subscriptions as $subscription) {
            $this->processSingleRenewal($subscription);
        }
    }

    public function processSingleRenewal(object $subscription): bool {
        try {
            // iyzico ile ödeme işlemi
            $payment_result = $this->processPayment($subscription);
            
            if ($payment_result['success']) {
                // Başarılı ödeme
                $this->subscriptionRepository->processSuccessfulPayment($subscription->id);
                
                // Yeni sipariş oluştur
                $this->createRenewalOrder($subscription);
                
                // E-posta bildirimi gönder
                $this->emailService->sendRenewalSuccessEmail($subscription);
                
                do_action('iyzico_subscription_renewal_success', $subscription);
                $this->logPaymentAttempt($subscription->id, $subscription->amount, $subscription->currency ?? 'TRY', 'success', $payment_result['payment_id'] ?? null);
                return true;
                
            } else {
                // Başarısız ödeme
                $this->subscriptionRepository->incrementFailedPayments($subscription->id);
                
                // E-posta bildirimi gönder
                $this->emailService->sendRenewalFailedEmail($subscription, $payment_result['error']);
                
                do_action('iyzico_subscription_renewal_failed', $subscription, $payment_result['error']);
                $this->logPaymentAttempt($subscription->id, $subscription->amount, $subscription->currency ?? 'TRY', 'failed', null, null, $payment_result['error']);
                return false;
            }
            
        } catch (\Exception $e) {
            error_log('Subscription renewal error: ' . $e->getMessage());
            $this->subscriptionRepository->incrementFailedPayments($subscription->id);
            $this->logPaymentAttempt($subscription->id, $subscription->amount, $subscription->currency ?? 'TRY', 'failed', null, null, $e->getMessage());
            return false;
        }
    }

    private function processPayment(object $subscription): array {
        // iyzico API ile ödeme işlemi
        try {
            $options = new \Iyzipay\Options();
            $options->setApiKey($this->gateway->api_key);
            $options->setSecretKey($this->gateway->secret_key);
            $options->setBaseUrl($this->gateway->sandbox === 'yes' ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com');

            // Stored card ile ödeme
            $request = new \Iyzipay\Request\CreatePaymentRequest();
            $request->setLocale(\Iyzipay\Model\Locale::TR);
            $request->setConversationId($subscription->id . '_renewal_' . time());
            $request->setPrice($subscription->amount);
            $request->setPaidPrice($subscription->amount);
            $request->setCurrency(\Iyzipay\Model\Currency::TL);
            $request->setInstallment(1);
            $request->setBasketId($subscription->id);
            $request->setPaymentChannel(\Iyzipay\Model\PaymentChannel::WEB);
            $request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::SUBSCRIPTION);

            // Müşteri bilgileri
            $user = get_user_by('id', $subscription->user_id);
            $buyer = new \Iyzipay\Model\Buyer();
            $buyer->setId($subscription->user_id);
            $buyer->setName($user->first_name);
            $buyer->setSurname($user->last_name);
            $buyer->setEmail($user->user_email);
            $buyer->setIdentityNumber('11111111111');
            $buyer->setRegistrationAddress('Adres');
            $buyer->setCity('İstanbul');
            $buyer->setCountry('Türkiye');
            $buyer->setIp('127.0.0.1');
            $request->setBuyer($buyer);

            // Sepet öğeleri
            $basketItems = array();
            $basketItem = new \Iyzipay\Model\BasketItem();
            $basketItem->setId($subscription->product_id);
            $basketItem->setName(get_the_title($subscription->product_id));
            $basketItem->setCategory1('Abonelik');
            $basketItem->setItemType(\Iyzipay\Model\BasketItemType::VIRTUAL);
            $basketItem->setPrice($subscription->amount);
            $basketItems[] = $basketItem;
            $request->setBasketItems($basketItems);

            // Kayıtlı kart ile ödeme (gerçek implementasyonda card token kullanılacak)
            $paymentCard = new \Iyzipay\Model\PaymentCard();
            $paymentCard->setCardUserKey($this->getCardUserKey($subscription->user_id));
            $paymentCard->setCardToken($this->getCardToken($subscription->user_id));
            $request->setPaymentCard($paymentCard);

            $payment = \Iyzipay\Model\Payment::create($request, $options);

            if ($payment->getStatus() === 'success') {
                return ['success' => true, 'payment_id' => $payment->getPaymentId()];
            } else {
                return ['success' => false, 'error' => $payment->getErrorMessage()];
            }

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function createRenewalOrder(object $subscription): object {
        $order = wc_create_order();
        $product = wc_get_product($subscription->product_id);
        
        $order->add_product($product, 1);
        $order->set_customer_id($subscription->user_id);
        $order->set_payment_method('iyzico');
        $order->set_payment_method_title('iyzico');
        $order->set_status('completed');
        
        // Meta data ekle
        $order->add_meta_data('_subscription_renewal', true);
        $order->add_meta_data('_parent_subscription_id', $subscription->id);
        
        $order->calculate_totals();
        $order->save();
        
        return $order;
    }

    public function retryFailedPayments(): void {
        $failed_subscriptions = $this->subscriptionRepository->findByStatus('suspended');
        
        foreach ($failed_subscriptions as $subscription) {
            // Sadece 3 günden eski askıya alınmış abonelikleri yeniden dene
            $suspended_date = new \DateTime($subscription->updated_at);
            $now = new \DateTime();
            $diff = $now->diff($suspended_date);
            
            if ($diff->days >= 3 && $subscription->failed_payments < 5) {
                $this->processSingleRenewal($subscription);
            }
        }
    }

    private function getCardUserKey(int $user_id): string {
        // Kullanıcının kayıtlı kart anahtarını getir
        return get_user_meta($user_id, '_iyzico_card_user_key', true);
    }

    private function getCardToken(int $user_id): string {
        // Kullanıcının kayıtlı kart token'ını getir
        return get_user_meta($user_id, '_iyzico_card_token', true);
    }

    public function cancelSubscription(int $subscription_id): bool {
        $subscription = $this->subscriptionRepository->find($subscription_id);
        if ($subscription) {
            $this->subscriptionRepository->cancel($subscription_id);
            
            // iyzico'da aboneliği iptal et
            $this->cancelIyzicoSubscription($subscription->iyzico_subscription_id);
            
            do_action('iyzico_subscription_cancelled', $subscription);
            return true;
        }
        return false;
    }

    public function suspendSubscription(int $subscription_id): bool {
        $subscription = $this->subscriptionRepository->find($subscription_id);
        if ($subscription) {
            $this->subscriptionRepository->suspend($subscription_id);
            do_action('iyzico_subscription_suspended', $subscription);
            return true;
        }
        return false;
    }

    public function reactivateSubscription(int $subscription_id): bool {
        $subscription = $this->subscriptionRepository->find($subscription_id);
        if (!$subscription) {
            return false;
        }
        // Askıda veya iptal statüsünde ise ödeme tetikle
        if (in_array($subscription->status, ['suspended', 'cancelled'], true)) {
            $result = $this->processSingleRenewal($subscription);
            if ($result) {
                // Başarılı ödeme sonrası statüyü aktive et
                return $this->subscriptionRepository->reactivate($subscription_id);
            }
            return false;
        }
        // Aktif değilse sadece aktive et
        return $this->subscriptionRepository->reactivate($subscription_id);
    }

    private function cancelIyzicoSubscription(string $iyzico_subscription_id): void {
        // iyzico API ile abonelik iptal işlemi
        // Bu kısım iyzico'nun subscription API'si kullanılarak implement edilecek
    }

    private function logPaymentAttempt(int $subscription_id, float $amount, string $currency, string $status, ?string $iyzico_payment_id = null, ?string $error_code = null, ?string $error_message = null): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'iyzico_subscription_payments',
            [
                'subscription_id' => $subscription_id,
                'order_id' => null,
                'iyzico_payment_id' => $iyzico_payment_id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'error_code' => $error_code,
                'error_message' => $error_message,
                'created_at' => current_time('mysql'),
            ],
            [
                '%d','%d','%s','%f','%s','%s','%s','%s'
            ]
        );
    }
} 