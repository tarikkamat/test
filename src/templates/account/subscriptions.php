<?php
/**
 * Müşteri Abonelik Sayfası Template
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="woocommerce-account-subscriptions">
    <h2><?php _e('Aboneliklerim', 'iyzico-subscription'); ?></h2>
    
    <?php if (!empty($user_subscriptions)): ?>
        <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
            <thead>
                <tr>
                    <th class="subscription-id"><?php _e('Abonelik', 'iyzico-subscription'); ?></th>
                    <th class="subscription-status"><?php _e('Durum', 'iyzico-subscription'); ?></th>
                    <th class="subscription-next-payment"><?php _e('Sonraki Ödeme', 'iyzico-subscription'); ?></th>
                    <th class="subscription-total"><?php _e('Toplam', 'iyzico-subscription'); ?></th>
                    <th class="subscription-actions"><?php _e('İşlemler', 'iyzico-subscription'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_subscriptions as $subscription): ?>
                    <tr class="subscription">
                        <td class="subscription-id" data-title="<?php _e('Abonelik', 'iyzico-subscription'); ?>">
                            <a href="#subscription-<?php echo $subscription->id; ?>" class="subscription-link">
                                #<?php echo $subscription->id; ?>
                            </a>
                            <br>
                            <small><?php echo get_the_title($subscription->product_id); ?></small>
                        </td>
                        <td class="subscription-status" data-title="<?php _e('Durum', 'iyzico-subscription'); ?>">
                            <span class="status-<?php echo $subscription->status; ?>">
                                <?php echo $this->get_status_label($subscription->status); ?>
                            </span>
                        </td>
                        <td class="subscription-next-payment" data-title="<?php _e('Sonraki Ödeme', 'iyzico-subscription'); ?>">
                            <?php if ($subscription->status === 'active'): ?>
                                <?php echo date('d.m.Y', strtotime($subscription->next_payment)); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="subscription-total" data-title="<?php _e('Toplam', 'iyzico-subscription'); ?>">
                            <?php echo wc_price($subscription->amount); ?>
                            <small>/ <?php echo $this->get_period_label($subscription->period); ?></small>
                        </td>
                        <td class="subscription-actions" data-title="<?php _e('İşlemler', 'iyzico-subscription'); ?>">
                            <?php if ($subscription->status === 'active'): ?>
                                <a href="#" class="button suspend-subscription" data-subscription-id="<?php echo $subscription->id; ?>">
                                    <?php _e('Askıya Al', 'iyzico-subscription'); ?>
                                </a>
                                <a href="#" class="button cancel-subscription" data-subscription-id="<?php echo $subscription->id; ?>">
                                    <?php _e('İptal Et', 'iyzico-subscription'); ?>
                                </a>
                            <?php elseif ($subscription->status === 'suspended'): ?>
                                <a href="#" class="button reactivate-subscription" data-subscription-id="<?php echo $subscription->id; ?>">
                                    <?php _e('Yeniden Aktifleştir', 'iyzico-subscription'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- Abonelik Detayları -->
                    <tr id="subscription-<?php echo $subscription->id; ?>" class="subscription-details" style="display: none;">
                        <td colspan="5">
                            <div class="subscription-detail-content">
                                <h4><?php _e('Abonelik Detayları', 'iyzico-subscription'); ?></h4>
                                <div class="subscription-info">
                                    <div class="info-row">
                                        <strong><?php _e('Başlangıç Tarihi:', 'iyzico-subscription'); ?></strong>
                                        <?php echo date('d.m.Y H:i', strtotime($subscription->start_date)); ?>
                                    </div>
                                    <div class="info-row">
                                        <strong><?php _e('Periyot:', 'iyzico-subscription'); ?></strong>
                                        <?php echo $this->get_period_label($subscription->period); ?>
                                    </div>
                                    <?php if ($subscription->billing_cycles > 0): ?>
                                        <div class="info-row">
                                            <strong><?php _e('Döngü:', 'iyzico-subscription'); ?></strong>
                                            <?php echo $subscription->completed_cycles; ?> / <?php echo $subscription->billing_cycles; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($subscription->failed_payments > 0): ?>
                                        <div class="info-row">
                                            <strong><?php _e('Başarısız Ödemeler:', 'iyzico-subscription'); ?></strong>
                                            <?php echo $subscription->failed_payments; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($subscription->end_date): ?>
                                        <div class="info-row">
                                            <strong><?php _e('Bitiş Tarihi:', 'iyzico-subscription'); ?></strong>
                                            <?php echo date('d.m.Y H:i', strtotime($subscription->end_date)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
            <?php _e('Henüz hiç aboneliğiniz bulunmamaktadır.', 'iyzico-subscription'); ?>
        </div>
    <?php endif; ?>
</div>

<style>
.subscription-details {
    background-color: #f9f9f9;
}

.subscription-detail-content {
    padding: 20px;
}

.subscription-info .info-row {
    margin-bottom: 10px;
}

.status-active {
    color: #46b450;
    font-weight: bold;
}

.status-suspended {
    color: #ffb900;
    font-weight: bold;
}

.status-cancelled {
    color: #dc3232;
    font-weight: bold;
}

.status-pending {
    color: #00a0d2;
    font-weight: bold;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Abonelik detaylarını göster/gizle
    $('.subscription-link').click(function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $(target).toggle();
    });
    
    // Abonelik işlemleri
    $('.suspend-subscription, .cancel-subscription, .reactivate-subscription').click(function(e) {
        e.preventDefault();
        
        var action = '';
        var confirmMessage = '';
        
        if ($(this).hasClass('suspend-subscription')) {
            action = 'suspend';
            confirmMessage = '<?php _e('Aboneliği askıya almak istediğinizden emin misiniz?', 'iyzico-subscription'); ?>';
        } else if ($(this).hasClass('cancel-subscription')) {
            action = 'cancel';
            confirmMessage = '<?php _e('Aboneliği iptal etmek istediğinizden emin misiniz? Bu işlem geri alınamaz.', 'iyzico-subscription'); ?>';
        } else if ($(this).hasClass('reactivate-subscription')) {
            action = 'reactivate';
            confirmMessage = '<?php _e('Aboneliği yeniden aktifleştirmek istediğinizden emin misiniz?', 'iyzico-subscription'); ?>';
        }
        
        if (confirm(confirmMessage)) {
            var subscriptionId = $(this).data('subscription-id');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'iyzico_subscription_action',
                    subscription_id: subscriptionId,
                    subscription_action: action,
                    nonce: '<?php echo wp_create_nonce('iyzico_subscription_action'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php _e('Bir hata oluştu.', 'iyzico-subscription'); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e('Bir hata oluştu.', 'iyzico-subscription'); ?>');
                }
            });
        }
    });
});
</script>

<?php
// Helper metodları
function get_status_label($status) {
    $labels = [
        'active' => __('Aktif', 'iyzico-subscription'),
        'pending' => __('Bekliyor', 'iyzico-subscription'),
        'cancelled' => __('İptal', 'iyzico-subscription'),
        'suspended' => __('Askıda', 'iyzico-subscription'),
        'completed' => __('Tamamlandı', 'iyzico-subscription'),
    ];
    return $labels[$status] ?? $status;
}

function get_period_label($period) {
    $labels = [
        'day' => __('Günlük', 'iyzico-subscription'),
        'week' => __('Haftalık', 'iyzico-subscription'),
        'month' => __('Aylık', 'iyzico-subscription'),
        'year' => __('Yıllık', 'iyzico-subscription'),
    ];
    return $labels[$period] ?? $period;
}
?> 