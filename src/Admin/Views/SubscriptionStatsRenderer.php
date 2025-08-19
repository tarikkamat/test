<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Admin\Views;

class SubscriptionStatsRenderer {
    public function render(object $stats): void {
        ?>
        <div class="postbox-container" style="width: 100%;">
            <div class="meta-box-sortables">
                <div class="iyzico-stats-grid">
                    <?php $this->render_stat_card(
                        __('Toplam Abonelik', 'iyzico-subscription'),
                        isset($stats->total) ? $stats->total : 0,
                        'dashicons-groups',
                        'total-subscriptions'
                    ); ?>
                    
                    <?php $this->render_stat_card(
                        __('Aktif Abonelik', 'iyzico-subscription'),
                        isset($stats->active) ? $stats->active : 0,
                        'dashicons-yes-alt',
                        'active-subscriptions'
                    ); ?>
                    
                    <?php $this->render_stat_card(
                        __('Askıda', 'iyzico-subscription'),
                        isset($stats->suspended) ? $stats->suspended : 0,
                        'dashicons-warning',
                        'suspended-subscriptions'
                    ); ?>
                    
                    <?php $this->render_stat_card(
                        __('Aylık Gelir', 'iyzico-subscription'),
                        isset($stats->monthly_revenue) ? wc_price($stats->monthly_revenue) : wc_price(0),
                        'dashicons-chart-line',
                        'monthly-revenue',
                        true
                    ); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_stat_card(string $title, $value, string $icon, string $css_class, bool $is_money = false): void {
        ?>
        <div class="postbox iyzico-stat-card <?php echo esc_attr($css_class); ?>">
            <div class="postbox-header">
                <h2 class="hndle"><?php echo esc_html($title); ?></h2>
            </div>
            <div class="inside">
                <div class="stat-content">
                    <div class="stat-value">
                        <?php echo $is_money ? $value : number_format($value); ?>
                    </div>
                    <div class="stat-icon">
                        <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
