<?php
<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Gateway;

use WC_Payment_Gateway;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Locale;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Options;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzico\IyzipayWoocommerceSubscription\Models\SavedCardRepository;
use Iyzico\IyzipayWoocommerceSubscription\Models\SubscriptionFactory;

class IyzicoGateway extends WC_Payment_Gateway {
    public $api_key;
    public $secret_key;
    public $sandbox;
    private SavedCardRepository $savedCardRepository;

    public function __construct() {
        $this->id = 'iyzico_subscription';
        $this->icon = plugin_dir_url(__FILE__) . '../../assets/img/cards/mastercard-visa-amex.svg';
        $this->has_fields = true;
        $this->method_title = 'iyzico Abonelik';
        $this->method_description = 'iyzico ile abonelik ödemeleri';

        $this->supports = [
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
        ];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->api_key = $this->get_option('api_key');
        $this->secret_key = $this->get_option('secret_key');
        $this->sandbox = $this->get_option('sandbox');

        $this->savedCardRepository = new SavedCardRepository();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        
        // Webhook handler'ı doğru şekilde bağla
        add_action('woocommerce_api_iyzico_subscription', [$this, 'handle_api_request']);
        add_action('woocommerce_api_iyzico-subscription', [$this, 'handle_api_request']);
        
        // Hata mesajlarını göster
        add_action('woocommerce_before_checkout_form', [$this, 'display_checkout_errors']);
    }

    /**
     * Determine whether the payment method is available for use.
     * Blocks uses this to decide availability as well.
     */
    public function is_available() {
        if ('yes' !== $this->enabled) {
            return false;
        }
        if (is_admin()) {
            return true;
        }
        if (empty($this->api_key) || empty($this->secret_key)) {
            return false;
        }
        // Require at least one subscription product in cart/order to be eligible
        if (function_exists('WC') && WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = isset($cart_item['data']) ? $cart_item['data'] : null;
                if ($product && in_array($product->get_type(), ['subscription', 'variable-subscription'], true)) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Aktif/Pasif',
                'type' => 'checkbox',
                'label' => 'iyzico ödeme geçidini aktifleştir',
                'default' => 'no',
            ],
            'title' => [
                'title' => 'Başlık',
                'type' => 'text',
                'description' => 'Ödeme sayfasında görünecek başlık',
                'default' => 'Kredi Kartı ile Güvenli Ödeme',
                'desc_tip' => true,
            ],
            'description' => [
                'title' => 'Açıklama',
                'type' => 'textarea',
                'description' => 'Ödeme sayfasında görünecek açıklama',
                'default' => 'iyzico altyapısı ile kart bilgileriniz güvende. Visa, Mastercard ve Amex ile hızlı ve güvenli ödeme yapın.',
            ],
            'api_key' => [
                'title' => 'API Anahtarı',
                'type' => 'text',
                'description' => 'iyzico API anahtarınız',
            ],
            'secret_key' => [
                'title' => 'Gizli Anahtar',
                'type' => 'text',
                'description' => 'iyzico gizli anahtarınız',
            ],
            'sandbox' => [
                'title' => 'Test Modu',
                'type' => 'checkbox',
                'label' => 'Test modunu aktifleştir',
                'default' => 'yes',
            ],
        ];
        
        // Çeviriler yüklendikten sonra form alanlarını güncelle
        add_action('init', [$this, 'update_form_field_translations'], 10);
    }

    public function update_form_field_translations() {
        if (did_action('init') && function_exists('__')) {
            $this->form_fields['enabled']['title'] = __('Aktif/Pasif', 'iyzico-subscription');
            $this->form_fields['enabled']['label'] = __('iyzico ödeme geçidini aktifleştir', 'iyzico-subscription');
            $this->form_fields['title']['title'] = __('Başlık', 'iyzico-subscription');
            $this->form_fields['title']['description'] = __('Ödeme sayfasında görünecek başlık', 'iyzico-subscription');
            $this->form_fields['title']['default'] = __('Kredi Kartı ile Güvenli Ödeme', 'iyzico-subscription');
            $this->form_fields['description']['title'] = __('Açıklama', 'iyzico-subscription');
            $this->form_fields['description']['description'] = __('Ödeme sayfasında görünecek açıklama', 'iyzico-subscription');
            $this->form_fields['description']['default'] = __('iyzico altyapısı ile kart bilgileriniz güvende. Visa, Mastercard ve Amex ile hızlı ve güvenli ödeme yapın.', 'iyzico-subscription');
            $this->form_fields['api_key']['title'] = __('API Anahtarı', 'iyzico-subscription');
            $this->form_fields['api_key']['description'] = __('iyzico API anahtarınız', 'iyzico-subscription');
            $this->form_fields['secret_key']['title'] = __('Gizli Anahtar', 'iyzico-subscription');
            $this->form_fields['secret_key']['description'] = __('iyzico gizli anahtarınız', 'iyzico-subscription');
            $this->form_fields['sandbox']['title'] = __('Test Modu', 'iyzico-subscription');
            $this->form_fields['sandbox']['label'] = __('Test modunu aktifleştir', 'iyzico-subscription');
        }
    }

    public function handle_api_request() {
        if (!isset($_GET['wc-api']) || $_GET['wc-api'] !== 'iyzico_subscription') {
            return;
        }

        try {
            $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
            
            if (empty($token)) {
                wc_add_notice(__('Token bulunamadı.', 'iyzico-subscription'), 'error');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }

            $options = new Options();
            $options->setApiKey($this->api_key);
            $options->setSecretKey($this->secret_key);
            $options->setBaseUrl($this->sandbox === 'yes' ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com');

            $request = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
            $request->setLocale(\Iyzipay\Model\Locale::TR);
            $request->setConversationId($token);
            $request->setToken($token);

            $checkoutForm = \Iyzipay\Model\CheckoutForm::retrieve($request, $options);

            if ($checkoutForm->getStatus() === 'success' && $checkoutForm->getPaymentStatus() === 'SUCCESS') {
                $basket_id = $checkoutForm->getBasketId();
                // Sepet ID'sinden sipariş ID'sini ayıkla
                $order_id = explode('_', $basket_id)[0];
                $order = wc_get_order($order_id);

                if (!$order) {
                    wc_add_notice(__('Sipariş bulunamadı.', 'iyzico-subscription'), 'error');
                    wp_safe_redirect(wc_get_checkout_url());
                    exit;
                }

                // Kart bilgilerini kontrol et
                $card_token = method_exists($checkoutForm, 'getCardToken') ? $checkoutForm->getCardToken() : null;
                $card_user_key = method_exists($checkoutForm, 'getCardUserKey') ? $checkoutForm->getCardUserKey() : null;
                
                if (!$card_token || !$card_user_key) {
                    // Ödemeyi iptal et
                    $options = new Options();
                    $options->setApiKey($this->api_key);
                    $options->setSecretKey($this->secret_key);
                    $options->setBaseUrl($this->sandbox === 'yes' ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com');

                    $cancelRequest = new \Iyzipay\Request\CreateCancelRequest();
                    $cancelRequest->setLocale(\Iyzipay\Model\Locale::TR);
                    $cancelRequest->setConversationId($order->get_id() . '_cancel_' . time());
                    $cancelRequest->setPaymentId($checkoutForm->getPaymentId());
                    $cancelRequest->setIp(isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');

                    $cancel = \Iyzipay\Model\Cancel::create($cancelRequest, $options);

                    if ($cancel->getStatus() === 'success') {
                        $order->update_status('cancelled', __('Kart bilgileri alınamadığı için ödeme iptal edildi.', 'iyzico-subscription'));
                        
                        // URL parametresi ile hata mesajını geç
                        $checkout_url = add_query_arg([
                            'iyzico_error' => 'card_info_missing',
                            'order_id' => $order->get_id()
                        ], wc_get_checkout_url());
                        
                        wp_safe_redirect($checkout_url);
                    } else {
                        $error_message = $cancel->getErrorMessage();
                        $error_code = $cancel->getErrorCode();
                        
                        // Siparişe not ekle
                        /* translators: 1: error code, 2: error message */
                        $order->add_order_note(sprintf(
                            __('Ödeme iptal edilemedi. Hata Kodu: %1$s, Hata: %2$s', 'iyzico-subscription'),
                            $error_code,
                            $error_message
                        ));
                        
                        // Hata detaylarını logla
                        error_log(sprintf(
                            'iyzico Cancel Error - Order ID: %s, Error Code: %s, Error Message: %s',
                            $order->get_id(),
                            $error_code,
                            $error_message
                        ));

                        // URL parametresi ile hata mesajını geç
                        $checkout_url = add_query_arg([
                            'iyzico_error' => 'cancel_failed',
                            'error_code' => $error_code,
                            'error_message' => urlencode($error_message),
                            'order_id' => $order->get_id()
                        ], wc_get_checkout_url());
                        
                        wp_safe_redirect($checkout_url);
                    }
                    exit;
                }

                // Kart bilgilerini kaydet (özel tabloya yaz)
                if ($card_user_key || $card_token) {
                    $this->persistSavedCard((int) $order->get_customer_id(), (string) $card_user_key, (string) $card_token);
                }

                // Siparişi tamamlandı olarak işaretle
                $order->payment_complete($checkoutForm->getPaymentId());
                /* translators: 1: payment id */
                $order->add_order_note(sprintf(
                    __('iyzico ödemesi tamamlandı. Ödeme ID: %1$s', 'iyzico-subscription'),
                    $checkoutForm->getPaymentId()
                ));

                // Abonelik kaydını oluştur (EmailService tetiklenmesi için action içerir)
                $this->create_subscription($order);

                // Siparişi tamamlandı olarak işaretle
                $order->update_status('completed', __('Ödeme başarıyla tamamlandı ve abonelik başlatıldı.', 'iyzico-subscription'));

                // E-posta EmailService tarafından action ile tetiklenir

                wp_redirect($this->get_return_url($order));
                exit;
            } else {
                // URL parametresi ile hata mesajını geç
                $checkout_url = add_query_arg([
                    'iyzico_error' => 'payment_failed'
                ], wc_get_checkout_url());
                
                wp_safe_redirect($checkout_url);
                exit;
            }
        } catch (\Exception $e) {
            // URL parametresi ile hata mesajını geç
            $checkout_url = add_query_arg([
                'iyzico_error' => 'general_error',
                'error_message' => urlencode($e->getMessage())
            ], wc_get_checkout_url());
            
            wp_safe_redirect($checkout_url);
            exit;
        }
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        $options = $this->buildIyzipayOptions();
        list($basketItems, $total_amount) = $this->buildBasketItemsAndTotal($order);
        $request = $this->buildCheckoutInitializeRequest($order, $basketItems, $total_amount, true);

        $checkoutFormInitialize = CheckoutFormInitialize::create($request, $options);

        if ($checkoutFormInitialize->getStatus() === 'success') {
            return array(
                'result' => 'success',
                'redirect' => $checkoutFormInitialize->getPaymentPageUrl()
            );
        }

        $errorMessage = method_exists($checkoutFormInitialize, 'getErrorMessage') ? $checkoutFormInitialize->getErrorMessage() : __('Ödeme başlatılamadı.', 'iyzico-subscription');
        $errorCode = method_exists($checkoutFormInitialize, 'getErrorCode') ? $checkoutFormInitialize->getErrorCode() : '';

        if ($errorCode === '3005' || stripos($errorMessage, 'cardUserKey') !== false) {
            $this->purgeCustomerCardCredentials((int) $order->get_customer_id());

            $requestWithoutCardKey = $this->buildCheckoutInitializeRequest($order, $basketItems, $total_amount, false);
            $retryInitialize = CheckoutFormInitialize::create($requestWithoutCardKey, $options);

            if ($retryInitialize->getStatus() === 'success') {
                return array(
                    'result' => 'success',
                    'redirect' => $retryInitialize->getPaymentPageUrl()
                );
            }

            $errorMessage = method_exists($retryInitialize, 'getErrorMessage') ? $retryInitialize->getErrorMessage() : $errorMessage;
            $errorCode = method_exists($retryInitialize, 'getErrorCode') ? $retryInitialize->getErrorCode() : $errorCode;
        }

        wc_add_notice(sprintf(__('Ödeme başlatılamadı (%1$s): %2$s', 'iyzico-subscription'), $errorCode, $errorMessage), 'error');
        return array(
            'result' => 'fail',
            'messages' => sprintf(__('Ödeme başlatılamadı (%1$s): %2$s', 'iyzico-subscription'), $errorCode, $errorMessage),
            'redirect' => ''
        );
    }

    private function buildIyzipayOptions(): Options {
        $options = new Options();
        $options->setApiKey($this->api_key);
        $options->setSecretKey($this->secret_key);
        $options->setBaseUrl($this->sandbox === 'yes' ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com');
        return $options;
    }

    private function buildBasketItemsAndTotal(\WC_Order $order): array {
        $basketItems = array();
        $total_amount = 0;

        foreach ($order->get_items() as $item) {
            $basketItem = new BasketItem();
            $basketItem->setId($item->get_product_id());
            $basketItem->setName($item->get_name());
            $basketItem->setCategory1('Genel');
            $basketItem->setItemType(BasketItemType::PHYSICAL);
            $basketItem->setPrice($item->get_total());
            $basketItems[] = $basketItem;
            $total_amount += $item->get_total();
        }

        if ($order->get_shipping_total() > 0) {
            $shippingItem = new BasketItem();
            $shippingItem->setId('shipping');
            $shippingItem->setName('Kargo Ücreti');
            $shippingItem->setCategory1('Kargo');
            $shippingItem->setItemType(BasketItemType::PHYSICAL);
            $shippingItem->setPrice($order->get_shipping_total());
            $basketItems[] = $shippingItem;
            $total_amount += $order->get_shipping_total();
        }

        if ($order->get_total_tax() > 0) {
            $taxItem = new BasketItem();
            $taxItem->setId('tax');
            $taxItem->setName('Vergi');
            $taxItem->setCategory1('Vergi');
            $taxItem->setItemType(BasketItemType::PHYSICAL);
            $taxItem->setPrice($order->get_total_tax());
            $basketItems[] = $taxItem;
            $total_amount += $order->get_total_tax();
        }

        return array($basketItems, (float) $total_amount);
    }

    private function buildCheckoutInitializeRequest(\WC_Order $order, array $basketItems, float $total_amount, bool $attachCardKey): CreateCheckoutFormInitializeRequest {
        $request = new CreateCheckoutFormInitializeRequest();
        $request->setLocale(Locale::TR);
        $request->setConversationId($order->get_id());
        $request->setBasketItems($basketItems);
        $request->setPrice($total_amount);
        $request->setPaidPrice($total_amount);
        $request->setCurrency($order->get_currency());
        $request->setBasketId($order->get_id() . '_' . time());
        $request->setPaymentGroup(PaymentGroup::PRODUCT);
        $request->setCallbackUrl(add_query_arg('wc-api', 'iyzico_subscription', home_url('/')));
        $request->setEnabledInstallments(array(1));

        if ($attachCardKey) {
            $existing_card_user_key = $this->getCardUserKeyFromStorage((int) $order->get_customer_id());
            if (!empty($existing_card_user_key) && method_exists($request, 'setCardUserKey')) {
                $request->setCardUserKey($existing_card_user_key);
            }
        }

        $buyer = new Buyer();
        $buyer->setId($order->get_customer_id());
        $buyer->setName($order->get_billing_first_name());
        $buyer->setSurname($order->get_billing_last_name());
        $buyer->setEmail($order->get_billing_email());
        $buyer->setIdentityNumber('11111111111');
        $buyer->setRegistrationAddress($order->get_billing_address_1());
        $buyer->setCity($order->get_billing_city());
        $buyer->setCountry($order->get_billing_country());
        $buyer->setIp(isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');
        $request->setBuyer($buyer);

        $shippingAddress = new \Iyzipay\Model\Address();
        $shippingAddress->setContactName($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
        $shippingAddress->setCity($order->get_shipping_city());
        $shippingAddress->setCountry($order->get_shipping_country());
        $shippingAddress->setAddress($order->get_shipping_address_1());
        $request->setShippingAddress($shippingAddress);

        $billingAddress = new \Iyzipay\Model\Address();
        $billingAddress->setContactName($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $billingAddress->setCity($order->get_billing_city());
        $billingAddress->setCountry($order->get_billing_country());
        $billingAddress->setAddress($order->get_billing_address_1());
        $request->setBillingAddress($billingAddress);

        return $request;
    }

    private function getCardUserKeyFromStorage(int $user_id): string {
        global $wpdb;
        $table = $wpdb->prefix . 'iyzico_saved_cards';
        $row = $wpdb->get_row($wpdb->prepare("SELECT card_user_key FROM $table WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id));
        if ($row && !empty($row->card_user_key)) {
            return (string) $row->card_user_key;
        }
        return (string) get_user_meta($user_id, '_iyzico_card_user_key', true);
    }

    private function purgeCustomerCardCredentials(int $user_id): void {
        delete_user_meta($user_id, '_iyzico_card_user_key');
        delete_user_meta($user_id, '_iyzico_card_token');

        global $wpdb;
        $table = $wpdb->prefix . 'iyzico_saved_cards';
        $wpdb->delete($table, array('user_id' => $user_id));
        return (string) ($this->savedCardRepository->getCardUserKey($user_id) ?? '');
    }

    private function purgeCustomerCardCredentials(int $user_id): void {
        $this->savedCardRepository->purgeForUser($user_id);
    }

    private function create_subscription($order) {
        $subscriptionService = SubscriptionFactory::createSubscriptionService();
        $subscriptionRepository = SubscriptionFactory::createSubscriptionRepository();

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            if (!$product || !in_array($product->get_type(), ['subscription', 'variable-subscription'])) {
                continue;
            }

            $period = get_post_meta($product->get_id(), '_subscription_period', true) ?: 'month';
            $length = get_post_meta($product->get_id(), '_subscription_length', true) ?: 0;

            $start_date = current_time('mysql');
            $next_payment_date = $this->calculate_next_payment_date($product);

            $subscription_data = [
                'user_id' => $order->get_customer_id(),
                'order_id' => $order->get_id(),
                'product_id' => $product->get_id(),
                'iyzico_subscription_id' => 'iyz_sub_' . $order->get_id() . '_' . time(),
                'status' => 'active',
                'amount' => $item->get_total(),
                'currency' => $order->get_currency(),
                'period' => $period,
                'period_interval' => 1,
                'start_date' => $start_date,
                'next_payment' => $next_payment_date,
                'end_date' => null,
                'trial_end_date' => null,
                'payment_method' => 'iyzico',
                'billing_cycles' => $length > 0 ? $length : 0,
            ];

            $subscription_id = $subscriptionService->createSubscription($subscription_data);

            if ($subscription_id) {
                $subscription = $subscriptionRepository->find((int) $subscription_id);
                if (did_action('init') && function_exists('__')) {
                    $note = sprintf(__('Abonelik oluşturuldu. Abonelik ID: %s', 'iyzico-subscription'), $subscription_id);
                } else {
                    $note = sprintf('Abonelik oluşturuldu. Abonelik ID: %s', $subscription_id);
                }
                $order->add_order_note($note);
                if ($subscription) {
                    do_action('iyzico_subscription_created', $subscription);
                }
            } else {
                error_log('Abonelik oluşturulamadı. Order ID: ' . $order->get_id());
                error_log('Subscription data: ' . print_r($subscription_data, true));
            }
        }
    }

    

    private function calculate_next_payment_date($product) {
        $period = get_post_meta($product->get_id(), '_subscription_period', true);
        $length = get_post_meta($product->get_id(), '_subscription_length', true);
        
        $next_date = current_time('mysql');
        
        switch ($period) {
            case 'day':
                $next_date = gmdate('Y-m-d H:i:s', strtotime($next_date . ' +1 day'));
                break;
            case 'week':
                $next_date = gmdate('Y-m-d H:i:s', strtotime($next_date . ' +1 week'));
                break;
            case 'month':
                $next_date = gmdate('Y-m-d H:i:s', strtotime($next_date . ' +1 month'));
                break;
            case 'year':
                $next_date = gmdate('Y-m-d H:i:s', strtotime($next_date . ' +1 year'));
                break;
        }
        
        return $next_date;
    }

    public function display_checkout_errors() {
        if (isset($_GET['iyzico_error'])) {
            $error_type = sanitize_text_field($_GET['iyzico_error']);
            $order_id = isset($_GET['order_id']) ? sanitize_text_field($_GET['order_id']) : '';
            $error_code = isset($_GET['error_code']) ? sanitize_text_field($_GET['error_code']) : '';
            $error_message = isset($_GET['error_message']) ? urldecode($_GET['error_message']) : '';
            
            $display_message = '';
            
            switch ($error_type) {
                case 'card_info_missing':
                    $display_message = __('Kart bilgileri alınamadığı için ödeme iptal edildi. Lütfen tekrar deneyiniz.', 'iyzico-subscription');
                    break;
                    
                case 'cancel_failed':
                    /* translators: 1: error code, 2: error message */
                    $display_message = sprintf(
                        __('Ödeme iptal edilemedi. Hata Kodu: %1$s, Hata: %2$s', 'iyzico-subscription'),
                        $error_code,
                        $error_message
                    );
                    break;
                    
                case 'payment_failed':
                    $display_message = __('Ödeme başarısız oldu. Lütfen tekrar deneyiniz.', 'iyzico-subscription');
                    break;
                    
                case 'general_error':
                    /* translators: 1: error message */
                    $display_message = sprintf(
                        __('İşlem sırasında bir hata oluştu: %1$s', 'iyzico-subscription'),
                        $error_message
                    );
                    break;
                    
                default:
                    $display_message = __('Bilinmeyen bir hata oluştu. Lütfen tekrar deneyiniz.', 'iyzico-subscription');
                    break;
            }
            
            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->add_order_note('iyzico ödeme hatası: ' . $display_message);
                }
            }
            
            wc_add_notice($display_message, 'error');
            
            // URL'den parametreleri temizle
            $clean_url = remove_query_arg(['iyzico_error', 'order_id', 'error_code', 'error_message']);
            if ($clean_url !== $_SERVER['REQUEST_URI']) {
                wp_safe_redirect($clean_url);
                exit;
            }
        }
    }

    private function persistSavedCard(int $user_id, string $card_user_key, string $card_token): void {
        global $wpdb;
        if (empty($user_id) || (empty($card_user_key) && empty($card_token))) {
            return;
        }
        $table = $wpdb->prefix . 'iyzico_saved_cards';
        // Eğer tablo yoksa meta'ya fallback
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$table_exists) {
            if (!empty($card_user_key)) {
                update_user_meta($user_id, '_iyzico_card_user_key', $card_user_key);
            }
            if (!empty($card_token)) {
                update_user_meta($user_id, '_iyzico_card_token', $card_token);
            }
            return;
        }
        // card_user_key güncellenirken mevcut kayıtlar silinmez; yeni satır eklenir ve UNIQUE(user_id, card_token) ile korunur
        $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'card_user_key' => $card_user_key,
                'card_token' => $card_token,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d','%s','%s','%s','%s']
        );
        $this->savedCardRepository->save($user_id, $card_user_key, $card_token);
    }
}