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
                        'total-subscriptions'
                    ); ?>
                    
                    <?php $this->render_stat_card(
                        __('Aktif Abonelik', 'iyzico-subscription'),
                        isset($stats->active) ? $stats->active : 0,
                        'active-subscriptions'
                    ); ?>
                    
                    <?php $this->render_stat_card(
                        __('Askıda', 'iyzico-subscription'),
                        isset($stats->suspended) ? $stats->suspended : 0,
                        'suspended-subscriptions'
                    ); ?>
                    
                    <?php $this->render_stat_card(
                        __('Aylık Gelir', 'iyzico-subscription'),
                        isset($stats->monthly_revenue) ? wc_price($stats->monthly_revenue) : wc_price(0),
                        'monthly-revenue',
                        true
                    ); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_stat_card(string $title, $value, string $css_class, bool $is_money = false): void {
        ?>
        <div class="postbox iyzico-stat-card <?php echo esc_attr($css_class); ?>">
            <div class="postbox-header">
                <h2 class="hndle"><?php echo esc_html($title); ?></h2>
            </div>
            <div class="inside">
                <div class="stat-content">
                    <div class="iyzico-stat-value">
                        <?php echo $is_money ? $value : number_format($value); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
