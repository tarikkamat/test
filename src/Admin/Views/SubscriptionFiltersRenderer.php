<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Admin\Views;

class SubscriptionFiltersRenderer {
    public function render(array $filters): void {
        ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" class="iyzico-filters-form">
                    <input type="hidden" name="page" value="iyzico-subscriptions">
                    
                    <label for="status-filter" class="screen-reader-text">
                        <?php _e('Durum filtresi', 'iyzico-subscription'); ?>
                    </label>
                    <select name="status" id="status-filter">
                        <option value=""><?php _e('Tüm Durumlar', 'iyzico-subscription'); ?></option>
                        <option value="active" <?php selected($filters['status'], 'active'); ?>>
                            <?php _e('Aktif', 'iyzico-subscription'); ?>
                        </option>
                        <option value="suspended" <?php selected($filters['status'], 'suspended'); ?>>
                            <?php _e('Askıda', 'iyzico-subscription'); ?>
                        </option>
                        <option value="cancelled" <?php selected($filters['status'], 'cancelled'); ?>>
                            <?php _e('İptal Edildi', 'iyzico-subscription'); ?>
                        </option>
                        <option value="expired" <?php selected($filters['status'], 'expired'); ?>>
                            <?php _e('Süresi Doldu', 'iyzico-subscription'); ?>
                        </option>
                    </select>
                    
                    <label for="customer-search" class="screen-reader-text">
                        <?php _e('Müşteri ara', 'iyzico-subscription'); ?>
                    </label>
                    <input type="search" name="customer_search" id="customer-search" 
                           placeholder="<?php _e('Müşteri ara...', 'iyzico-subscription'); ?>" 
                           value="<?php echo esc_attr($filters['customer_search']); ?>">
                    
                    <label for="date-from" class="screen-reader-text">
                        <?php _e('Başlangıç tarihi', 'iyzico-subscription'); ?>
                    </label>
                    <input type="date" name="date_from" id="date-from" 
                           value="<?php echo esc_attr($filters['date_from']); ?>">
                    
                    <label for="date-to" class="screen-reader-text">
                        <?php _e('Bitiş tarihi', 'iyzico-subscription'); ?>
                    </label>
                    <input type="date" name="date_to" id="date-to" 
                           value="<?php echo esc_attr($filters['date_to']); ?>">
                    
                    <input type="submit" class="button action" value="<?php _e('Filtrele', 'iyzico-subscription'); ?>">
                    <a href="?page=iyzico-subscriptions" class="button">
                        <?php _e('Temizle', 'iyzico-subscription'); ?>
                    </a>
                </form>
            </div>
        </div>
        <?php
    }
}
