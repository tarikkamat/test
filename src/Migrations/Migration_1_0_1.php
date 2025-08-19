<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Migrations;

/**
 * Örnek migration - v1.0.1
 * Yeni özellikler eklemek için kullanılır
 */
class Migration_1_0_1 implements MigrationInterface {
    
    public function up(): void {
        $this->add_new_column();
        $this->update_existing_data();
    }

    public function down(): void {
        global $wpdb;
        
        // Yeni kolonu kaldır
        $table_name = $wpdb->prefix . 'iyzico_subscriptions';
        $wpdb->query("ALTER TABLE $table_name DROP COLUMN IF EXISTS notes");
    }

    /**
     * Yeni kolon ekle
     */
    private function add_new_column(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iyzico_subscriptions';
        
        // Kolon zaten var mı kontrol et
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'notes'");
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN notes TEXT NULL AFTER failed_payments");
        }
    }

    /**
     * Mevcut verileri güncelle
     */
    private function update_existing_data(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iyzico_subscriptions';
        
        // Örnek: Tüm aktif aboneliklerin notlarına varsayılan değer ekle
        $wpdb->query("UPDATE $table_name SET notes = 'Otomatik migration ile güncellendi' WHERE notes IS NULL");
    }
}
