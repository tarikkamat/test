<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Services;

use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\AccountServiceInterface;
use Iyzico\IyzipayWoocommerceSubscription\Models\Interfaces\SubscriptionRepositoryInterface;

class AccountService implements AccountServiceInterface
{
    private SubscriptionRepositoryInterface $subscriptionRepository;

    public function __construct(SubscriptionRepositoryInterface $subscriptionRepository) {
        $this->subscriptionRepository = $subscriptionRepository;
    }

    public function addAccountEndpoints(): void
    {
        add_rewrite_endpoint('subscriptions', EP_ROOT | EP_PAGES);
    }

    public function addAccountMenuItems(array $items): array
    {
        $items['subscriptions'] = __('Aboneliklerim', 'iyzico-subscription');
        return $items;
    }

    public function renderSubscriptionsAccountPage(): void
    {
        $user_subscriptions = $this->subscriptionRepository->findByUser(get_current_user_id());
        
        if (empty($user_subscriptions)) {
            echo '<p>' . esc_html__('Henüz aktif aboneliğiniz bulunmamaktadır.', 'iyzico-subscription') . '</p>';
            return;
        }

        echo '<div class="woocommerce-account-subscriptions">';
        echo '<table class="woocommerce-orders-table shop_table shop_table_responsive">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Ürün', 'iyzico-subscription') . '</th>';
        echo '<th>' . esc_html__('Durum', 'iyzico-subscription') . '</th>';
        echo '<th>' . esc_html__('Başlangıç Tarihi', 'iyzico-subscription') . '</th>';
        echo '<th>' . esc_html__('Sonraki Ödeme', 'iyzico-subscription') . '</th>';
        echo '<th>' . esc_html__('Tutar', 'iyzico-subscription') . '</th>';
        echo '<th>' . esc_html__('İşlemler', 'iyzico-subscription') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($user_subscriptions as $subscription) {
            $product = wc_get_product($subscription->product_id);
            if (!$product) continue;

            echo '<tr>';
            echo '<td>' . esc_html($product->get_name()) . '</td>';
            echo '<td>' . esc_html($this->getStatusLabel($subscription->status)) . '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($subscription->start_date))) . '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date))) . '</td>';
            echo '<td>' . wp_kses_post(wc_price($subscription->amount)) . '</td>';
            echo '<td>';
            $this->renderSubscriptionActions($subscription);
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        // JavaScript kodunu ekle
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.iyzico-subscription-action').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('<?php echo esc_js(__('Bu işlemi gerçekleştirmek istediğinizden emin misiniz?', 'iyzico-subscription')); ?>')) {
                    return;
                }

                var $button = $(this);
                var subscriptionId = $button.data('subscription-id');
                var action = $button.data('action');

                $button.prop('disabled', true);

                $.ajax({
                    url: wc_add_to_cart_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'iyzico_subscription_action',
                        subscription_id: subscriptionId,
                        subscription_action: action,
                        nonce: '<?php echo esc_attr(wp_create_nonce('iyzico_subscription_action')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Bir hata oluştu. Lütfen tekrar deneyin.', 'iyzico-subscription')); ?>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function getStatusLabel(string $status): string
    {
        $labels = [
            'active' => __('Aktif', 'iyzico-subscription'),
            'cancelled' => __('İptal Edildi', 'iyzico-subscription'),
            'suspended' => __('Askıya Alındı', 'iyzico-subscription'),
            'expired' => __('Süresi Doldu', 'iyzico-subscription'),
        ];

        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    public function renderSubscriptionActions(object $subscription): void
    {
        if ($subscription->status === 'active') {
            echo '<button class="button iyzico-subscription-action" data-subscription-id="' . esc_attr($subscription->id) . '" data-action="suspend">' . __('Askıya Al', 'iyzico-subscription') . '</button> ';
            echo '<button class="button iyzico-subscription-action" data-subscription-id="' . esc_attr($subscription->id) . '" data-action="cancel">' . __('İptal Et', 'iyzico-subscription') . '</button>';
        } elseif ($subscription->status === 'suspended') {
            echo '<button class="button iyzico-subscription-action" data-subscription-id="' . esc_attr($subscription->id) . '" data-action="reactivate">' . __('Yeniden Aktifleştir', 'iyzico-subscription') . '</button>';
        }
    }
}