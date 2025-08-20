<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Admin;

use Iyzico\IyzipayWoocommerceSubscription\Admin\Views\SubscriptionAdminView;
use Iyzico\IyzipayWoocommerceSubscription\Services\SubscriptionAdminService;
use Iyzico\IyzipayWoocommerceSubscription\Services\RenewalService;
use Iyzipay\Model\CardList;
use Iyzipay\Model\CardInformation;
use Iyzipay\Model\Locale as IyziLocale;
use Iyzipay\Options;
use Iyzipay\Request\CreateCardRequest;
use Iyzipay\Request\RetrieveCardListRequest;

class SubscriptionAdminController {
    private SubscriptionAdminView $view;
    private SubscriptionAdminService $service;
    private RenewalService $renewalService;

    public function __construct(
        SubscriptionAdminView $view,
        SubscriptionAdminService $service,
        RenewalService $renewalService
    ) {
        $this->view = $view;
        $this->service = $service;
        $this->renewalService = $renewalService;
        
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_iyzico_subscription_admin_action', [$this, 'handle_admin_action']);
        add_action('wp_ajax_iyzico_trigger_payment', [$this, 'handle_trigger_payment']);
        // Saved cards admin AJAX
        add_action('wp_ajax_iyzico_admin_list_saved_cards', [$this, 'list_saved_cards']);
        add_action('wp_ajax_iyzico_admin_create_saved_card', [$this, 'create_saved_card']);
    }

    public function add_menu_page(): void {
        $menu_title = (did_action('init') && function_exists('__')) ? 
            __('Abonelikler', 'iyzico-subscription') : 
            'Abonelikler';
            
        add_submenu_page(
            'woocommerce',
            $menu_title,
            $menu_title,
            'manage_woocommerce',
            'iyzico-subscriptions',
            [$this->view, 'render_subscriptions_page']
        );
    }

    public function enqueue_admin_scripts(string $hook): void {
        error_log('enqueue_admin_scripts çağrıldı. Hook: ' . $hook);
        
        if ('woocommerce_page_iyzico-subscriptions' !== $hook) {
            error_log('Hook eşleşmedi. Beklenen: woocommerce_page_iyzico-subscriptions, Gelen: ' . $hook);
            return;
        }

        error_log('Hook eşleşti, admin assets yükleniyor...');
        $this->view->enqueue_admin_assets();
    }

    public function handle_admin_action(): void {
        // Debug log ekle
        error_log('iyzico_subscription_admin_action çağrıldı');
        error_log('POST verileri: ' . print_r($_POST, true));
        
        check_ajax_referer('iyzico_subscription_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            error_log('Kullanıcı yetkisi yok');
            wp_send_json_error(['message' => __('Yetkiniz yok.', 'iyzico-subscription')]);
        }

        $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
        $action = isset($_POST['subscription_action']) ? sanitize_text_field($_POST['subscription_action']) : '';

        error_log("Subscription ID: $subscription_id, Action: $action");

        if (!$subscription_id || !$action) {
            error_log('Geçersiz istek parametreleri');
            wp_send_json_error(['message' => __('Geçersiz istek.', 'iyzico-subscription')]);
        }

        $result = $this->service->performAction($subscription_id, $action);
        error_log("performAction sonucu: " . ($result ? 'true' : 'false'));

        if ($result) {
            wp_send_json_success(['message' => __('İşlem başarıyla tamamlandı.', 'iyzico-subscription')]);
        } else {
            wp_send_json_error(['message' => __('İşlem başarısız oldu.', 'iyzico-subscription')]);
        }
    }

    public function handle_trigger_payment(): void {
        check_ajax_referer('iyzico_trigger_payment', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Yetkiniz yok.', 'iyzico-subscription')]);
        }

        $subscription_id = intval($_POST['subscription_id']);
        
        $subscription = $this->service->getSubscriptionById($subscription_id);
        if (!$subscription) {
            wp_send_json_error(['message' => __('Abonelik bulunamadı.', 'iyzico-subscription')]);
        }
        
        $result = $this->renewalService->processSingleRenewal($subscription);

        if ($result) {
            wp_send_json_success(['message' => __('Ödeme başarıyla alındı.', 'iyzico-subscription')]);
        } else {
            wp_send_json_error(['message' => __('Ödeme alınamadı.', 'iyzico-subscription')]);
        }
    }

    private function get_iyzico_options(): ?Options {
        $settings = get_option('woocommerce_iyzico_subscription_settings', []);
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        $secret_key = isset($settings['secret_key']) ? $settings['secret_key'] : '';
        $sandbox = isset($settings['sandbox']) ? $settings['sandbox'] : 'yes';

        if (empty($api_key) || empty($secret_key)) {
            return null;
        }

        $options = new Options();
        $options->setApiKey($api_key);
        $options->setSecretKey($secret_key);
        $options->setBaseUrl($sandbox === 'yes' ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com');
        return $options;
    }

    public function list_saved_cards(): void {
        check_ajax_referer('iyzico_subscription_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Yetkiniz yok.', 'iyzico-subscription')]);
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_send_json_error(['message' => __('Geçersiz kullanıcı.', 'iyzico-subscription')]);
        }

        $card_user_key = get_user_meta($user_id, '_iyzico_card_user_key', true);
        if (empty($card_user_key)) {
            wp_send_json_success(['cards' => [], 'cardUserKey' => null]);
        }

        $options = $this->get_iyzico_options();
        if (!$options) {
            wp_send_json_error(['message' => __('iyzico API anahtarları yapılandırılmamış.', 'iyzico-subscription')]);
        }

        try {
            $request = new RetrieveCardListRequest();
            $request->setLocale(IyziLocale::TR);
            $request->setCardUserKey($card_user_key);

            $cardList = CardList::retrieve($request, $options);

            if ($cardList->getStatus() !== 'success') {
                $error = method_exists($cardList, 'getErrorMessage') ? $cardList->getErrorMessage() : __('Bilinmeyen hata', 'iyzico-subscription');
                /* translators: 1: error message */
                wp_send_json_error(['message' => sprintf(__('Kartlar alınamadı: %1$s', 'iyzico-subscription'), $error)]);
            }

            $details = $cardList->getCardDetails() ?: [];
            $cards = array_map(function ($c) {
                return [
                    'alias' => method_exists($c, 'getCardAlias') ? $c->getCardAlias() : '',
                    'association' => method_exists($c, 'getCardAssociation') ? $c->getCardAssociation() : '',
                    'family' => method_exists($c, 'getCardFamily') ? $c->getCardFamily() : '',
                    'bank' => method_exists($c, 'getCardBankName') ? $c->getCardBankName() : '',
                    'type' => method_exists($c, 'getCardType') ? $c->getCardType() : '',
                    'lastFour' => method_exists($c, 'getLastFourDigits') ? $c->getLastFourDigits() : '',
                    'token' => method_exists($c, 'getCardToken') ? $c->getCardToken() : '',
                    'bin' => method_exists($c, 'getBinNumber') ? $c->getBinNumber() : '',
                ];
            }, $details);

            wp_send_json_success([
                'cards' => $cards,
                'cardUserKey' => method_exists($cardList, 'getCardUserKey') ? $cardList->getCardUserKey() : $card_user_key,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function create_saved_card(): void {
        check_ajax_referer('iyzico_subscription_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Yetkiniz yok.', 'iyzico-subscription')]);
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $card_alias = isset($_POST['card_alias']) ? sanitize_text_field($_POST['card_alias']) : '';
        $card_holder = isset($_POST['card_holder_name']) ? sanitize_text_field($_POST['card_holder_name']) : '';
        $card_number = isset($_POST['card_number']) ? preg_replace('/\D+/', '', $_POST['card_number']) : '';
        $expire_month = isset($_POST['expire_month']) ? sanitize_text_field($_POST['expire_month']) : '';
        $expire_year = isset($_POST['expire_year']) ? sanitize_text_field($_POST['expire_year']) : '';

        if (!$user_id || empty($card_holder) || empty($card_number) || empty($expire_month) || empty($expire_year)) {
            wp_send_json_error(['message' => __('Eksik kart bilgileri.', 'iyzico-subscription')]);
        }
        if (!preg_match('/^\d{2}$/', $expire_month) || !preg_match('/^\d{4}$/', $expire_year) || strlen($card_number) < 12) {
            wp_send_json_error(['message' => __('Kart bilgileri geçersiz.', 'iyzico-subscription')]);
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(['message' => __('Kullanıcı bulunamadı.', 'iyzico-subscription')]);
        }

        $options = $this->get_iyzico_options();
        if (!$options) {
            wp_send_json_error(['message' => __('iyzico API anahtarları yapılandırılmamış.', 'iyzico-subscription')]);
        }

        $card_user_key = get_user_meta($user_id, '_iyzico_card_user_key', true);

        try {
            $cardInfo = new CardInformation();
            if (!empty($card_alias)) $cardInfo->setCardAlias($card_alias);
            $cardInfo->setCardHolderName($card_holder);
            $cardInfo->setCardNumber($card_number);
            $cardInfo->setExpireMonth($expire_month);
            $cardInfo->setExpireYear($expire_year);

            $request = new CreateCardRequest();
            $request->setLocale(IyziLocale::TR);
            $request->setExternalId('admin_' . $user_id . '_' . time());
            $request->setEmail($user->user_email);
            if (!empty($card_user_key)) {
                $request->setCardUserKey($card_user_key);
            }
            $request->setCard($cardInfo);

            $card = \Iyzipay\Model\Card::create($request, $options);

            if ($card->getStatus() !== 'success') {
                $error = method_exists($card, 'getErrorMessage') ? $card->getErrorMessage() : __('Bilinmeyen hata', 'iyzico-subscription');
                /* translators: 1: error message */
                wp_send_json_error(['message' => sprintf(__('Kart oluşturulamadı: %1$s', 'iyzico-subscription'), $error)]);
            }

            // Persist card user key and last token
            $new_card_user_key = method_exists($card, 'getCardUserKey') ? $card->getCardUserKey() : '';
            if (!empty($new_card_user_key) && empty($card_user_key)) {
                update_user_meta($user_id, '_iyzico_card_user_key', $new_card_user_key);
            }
            $card_token = method_exists($card, 'getCardToken') ? $card->getCardToken() : '';
            if (!empty($card_token)) {
                update_user_meta($user_id, '_iyzico_card_token', $card_token);
            }

            $response_card = [
                'alias' => method_exists($card, 'getCardAlias') ? $card->getCardAlias() : $card_alias,
                'association' => method_exists($card, 'getCardAssociation') ? $card->getCardAssociation() : '',
                'family' => method_exists($card, 'getCardFamily') ? $card->getCardFamily() : '',
                'bank' => method_exists($card, 'getCardBankName') ? $card->getCardBankName() : '',
                'type' => method_exists($card, 'getCardType') ? $card->getCardType() : '',
                'lastFour' => method_exists($card, 'getLastFourDigits') ? $card->getLastFourDigits() : substr($card_number, -4),
                'token' => $card_token,
                'bin' => method_exists($card, 'getBinNumber') ? $card->getBinNumber() : substr($card_number, 0, 6),
            ];

            wp_send_json_success(['message' => __('Kart başarıyla oluşturuldu.', 'iyzico-subscription'), 'card' => $response_card]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
