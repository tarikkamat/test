<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Admin\Views;

class SubscriptionTableRenderer {
    public function render(array $subscriptions): void {
        if (empty($subscriptions)) {
            $this->render_empty_state();
            return;
        }

        $this->render_bulk_actions();
        $this->render_table($subscriptions);
        $this->render_bulk_actions_bottom();
    }

    private function render_empty_state(): void {
        ?>
        <div class="postbox">
            <div class="inside">
                <div class="no-items">
                    <div class="dashicons dashicons-admin-post"></div>
                    <h3><?php _e('Abonelik Bulunamadı', 'iyzico-subscription'); ?></h3>
                    <p><?php _e('Henüz hiç abonelik kaydı bulunmamaktadır.', 'iyzico-subscription'); ?></p>
                    <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="button button-primary">
                        <?php _e('İlk Abonelik Ürününü Oluştur', 'iyzico-subscription'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_bulk_actions(): void {
        ?>
        <div class="tablenav-pages">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text">
                    <?php _e('Toplu işlem seç', 'iyzico-subscription'); ?>
                </label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Toplu İşlemler', 'iyzico-subscription'); ?></option>
                    <option value="suspend"><?php _e('Askıya Al', 'iyzico-subscription'); ?></option>
                    <option value="cancel"><?php _e('İptal Et', 'iyzico-subscription'); ?></option>
                    <option value="reactivate"><?php _e('Yeniden Aktifleştir', 'iyzico-subscription'); ?></option>
                </select>
                <input type="submit" id="doaction" class="button action" value="<?php _e('Uygula', 'iyzico-subscription'); ?>">
            </div>
        </div>
        <?php
    }

    private function render_table(array $subscriptions): void {
        ?>
        <div class="subscriptions-table-container">
            <form id="subscriptions-filter" method="get">
                <input type="hidden" name="page" value="iyzico-subscriptions">
                
                <table class="wp-list-table widefat fixed striped subscriptions">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column">
                                <label class="screen-reader-text" for="cb-select-all-1">
                                    <?php _e('Tümünü seç', 'iyzico-subscription'); ?>
                                </label>
                                <input id="cb-select-all-1" type="checkbox">
                            </td>
                            <th scope="col" class="manage-column column-id"><?php _e('ID', 'iyzico-subscription'); ?></th>
                            <th scope="col" class="manage-column column-customer"><?php _e('Müşteri', 'iyzico-subscription'); ?></th>
                            <th scope="col" class="manage-column column-product"><?php _e('Ürün', 'iyzico-subscription'); ?></th>
                            <th scope="col" class="manage-column column-status"><?php _e('Durum', 'iyzico-subscription'); ?></th>
                            <th scope="col" class="manage-column column-amount"><?php _e('Tutar', 'iyzico-subscription'); ?></th>
                            <th scope="col" class="manage-column column-period"><?php _e('Periyot', 'iyzico-subscription'); ?></th>
                            <th scope="col" class="manage-column column-start-date"><?php _e('Başlangıç', 'iyzico-subscription'); ?></th>
                            <th scope="col" class="manage-column column-next-payment"><?php _e('Sonraki Ödeme', 'iyzico-subscription'); ?></th>
                            <th scope="col" class="manage-column column-actions"><?php _e('İşlemler', 'iyzico-subscription'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="the-list">
                        <?php foreach ($subscriptions as $subscription) : ?>
                            <?php $this->render_table_row($subscription); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }

    private function render_table_row(object $subscription): void {
        $user = get_userdata($subscription->user_id);
        $product = wc_get_product($subscription->product_id);
        
        if (!$user || !$product) return;
        
        $next_payment_date = strtotime($subscription->next_payment);
        $today = strtotime('today');
        $days_diff = ($next_payment_date - $today) / (60 * 60 * 24);
        $is_urgent = ($days_diff <= 3 && $subscription->status === 'active');
        ?>
        <tr id="subscription-<?php echo $subscription->id; ?>" class="subscription-row">
            <th scope="row" class="check-column">
                <input type="checkbox" name="subscription[]" value="<?php echo $subscription->id; ?>">
            </th>
            <td class="column-id">
                <strong>#<?php echo $subscription->id; ?></strong>
            </td>
            <td class="column-customer">
                <?php $this->render_customer_cell($user); ?>
            </td>
            <td class="column-product">
                <?php $this->render_product_cell($product); ?>
            </td>
            <td class="column-status">
                <?php echo $this->render_status_badge($subscription->status); ?>
            </td>
            <td class="column-amount">
                <strong><?php echo wc_price($subscription->amount); ?></strong>
                <br>
                <span class="description"><?php echo strtoupper($subscription->currency ?? 'TRY'); ?></span>
            </td>
            <td class="column-period">
                <span class="subscription-period"><?php echo $this->get_period_label($subscription->period); ?></span>
            </td>
            <td class="column-start-date">
                <?php echo date_i18n('d/m/Y', strtotime($subscription->start_date)); ?>
            </td>
            <td class="column-next-payment">
                <?php $this->render_next_payment_cell($subscription->next_payment, $is_urgent, $days_diff); ?>
            </td>
            <td class="column-actions">
                <?php $this->render_actions_cell($subscription); ?>
            </td>
        </tr>
        <?php
    }

    private function render_customer_cell(\WP_User $user): void {
        ?>
        <div class="customer-info">
            <strong>
                <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" title="<?php echo esc_attr($user->display_name); ?>">
                    <?php echo esc_html(mb_strlen($user->display_name) > 15 ? mb_substr($user->display_name, 0, 15) . '...' : $user->display_name); ?>
                </a>
            </strong>
            <br>
            <span class="description"><?php echo esc_html(mb_strlen($user->user_email) > 20 ? mb_substr($user->user_email, 0, 20) . '...' : $user->user_email); ?></span>
        </div>
        <?php
    }

    private function render_product_cell(\WC_Product $product): void {
        ?>
        <div class="product-info">
            <strong>
                <a href="<?php echo get_edit_post_link($product->get_id()); ?>" title="<?php echo esc_attr($product->get_name()); ?>">
                    <?php echo esc_html(mb_strlen($product->get_name()) > 15 ? mb_substr($product->get_name(), 0, 15) . '...' : $product->get_name()); ?>
                </a>
            </strong>
            <br>
            <span class="description">ID: <?php echo $product->get_id(); ?></span>
        </div>
        <?php
    }

    private function render_status_badge(string $status): string {
        $badges = [
            'active' => '<span class="status-badge status-active">' . __('Aktif', 'iyzico-subscription') . '</span>',
            'suspended' => '<span class="status-badge status-suspended">' . __('Askıda', 'iyzico-subscription') . '</span>',
            'cancelled' => '<span class="status-badge status-cancelled">' . __('İptal Edildi', 'iyzico-subscription') . '</span>',
            'expired' => '<span class="status-badge status-expired">' . __('Süresi Doldu', 'iyzico-subscription') . '</span>',
        ];
        
        return $badges[$status] ?? '<span class="status-badge status-unknown">' . esc_html($status) . '</span>';
    }

    private function render_next_payment_cell(string $next_payment, bool $is_urgent, float $days_diff): void {
        ?>
        <span class="<?php echo $is_urgent ? 'urgent-payment' : ''; ?>">
            <?php echo date_i18n('d/m/Y', strtotime($next_payment)); ?>
        </span>
        <?php if ($is_urgent) : ?>
            <br><span class="description urgent-warning">
                <span class="dashicons dashicons-warning"></span>
                <?php printf(__('%dg', 'iyzico-subscription'), max(0, ceil($days_diff))); ?>
            </span>
        <?php endif; ?>
        <?php
    }

    private function render_actions_cell(object $subscription): void {
        ?>
        <div class="row-actions">
            <span class="view">
                <a href="#" class="view-subscription" data-subscription-id="<?php echo $subscription->id; ?>" title="<?php _e('Görüntüle', 'iyzico-subscription'); ?>">
                    <?php _e('Görüntüle', 'iyzico-subscription'); ?>
                </a>
            </span>
            
            <?php if ($subscription->status === 'active') : ?>
                | <span class="suspend">
                    <a href="#" class="subscription-action" data-subscription-id="<?php echo $subscription->id; ?>" data-action="suspend" title="<?php _e('Askıya Al', 'iyzico-subscription'); ?>">
                        <?php _e('Askıya Al', 'iyzico-subscription'); ?>
                    </a>
                </span>
                | <span class="cancel">
                    <a href="#" class="subscription-action" data-subscription-id="<?php echo $subscription->id; ?>" data-action="cancel" title="<?php _e('İptal Et', 'iyzico-subscription'); ?>">
                        <?php _e('İptal Et', 'iyzico-subscription'); ?>
                    </a>
                </span>
            <?php elseif ($subscription->status === 'suspended') : ?>
                | <span class="reactivate">
                    <a href="#" class="subscription-action" data-subscription-id="<?php echo $subscription->id; ?>" data-action="reactivate" title="<?php _e('Yeniden Aktifleştir', 'iyzico-subscription'); ?>">
                        <?php _e('Yeniden Aktifleştir', 'iyzico-subscription'); ?>
                    </a>
                </span>
            <?php endif; ?>
            
            <!-- TEST: İptal edilmiş abonelikler için de action butonları ekle -->
            <?php if ($subscription->status === 'cancelled') : ?>
                | <span class="reactivate">
                    <a href="#" class="subscription-action" data-subscription-id="<?php echo $subscription->id; ?>" data-action="reactivate" title="<?php _e('Yeniden Aktifleştir (Test)', 'iyzico-subscription'); ?>">
                        <?php _e('Yeniden Aktifleştir (Test)', 'iyzico-subscription'); ?>
                    </a>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_bulk_actions_bottom(): void {
        ?>
        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-bottom" class="screen-reader-text">
                    <?php _e('Toplu işlem seç', 'iyzico-subscription'); ?>
                </label>
                <select name="action2" id="bulk-action-selector-bottom">
                    <option value="-1"><?php _e('Toplu İşlemler', 'iyzico-subscription'); ?></option>
                    <option value="suspend"><?php _e('Askıya Al', 'iyzico-subscription'); ?></option>
                    <option value="cancel"><?php _e('İptal Et', 'iyzico-subscription'); ?></option>
                    <option value="reactivate"><?php _e('Yeniden Aktifleştir', 'iyzico-subscription'); ?></option>
                </select>
                <input type="submit" id="doaction2" class="button action" value="<?php _e('Uygula', 'iyzico-subscription'); ?>">
            </div>
        </div>
        <?php
    }

    private function get_period_label(string $period): string {
        $labels = [
            'day' => __('Günlük', 'iyzico-subscription'),
            'week' => __('Haftalık', 'iyzico-subscription'),
            'month' => __('Aylık', 'iyzico-subscription'),
            'year' => __('Yıllık', 'iyzico-subscription'),
        ];
        
        return $labels[$period] ?? $period;
    }
}
