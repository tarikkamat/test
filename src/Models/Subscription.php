<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Models;

class Subscription {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'iyzico_subscriptions';
    }



    public function create($data) {
        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'user_id' => $data['user_id'],
                'order_id' => $data['order_id'],
                'product_id' => $data['product_id'],
                'iyzico_subscription_id' => $data['iyzico_subscription_id'],
                'status' => $data['status'] ?? 'pending',
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'TRY',
                'period' => $data['period'],
                'period_interval' => $data['period_interval'] ?? 1,
                'start_date' => $data['start_date'],
                'next_payment' => $data['next_payment'],
                'end_date' => $data['end_date'] ?? null,
                'trial_end_date' => $data['trial_end_date'] ?? null,
                'payment_method' => $data['payment_method'],
                'billing_cycles' => $data['billing_cycles'] ?? 0,
            ],
            [
                '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%d',
                '%s', '%s', '%s', '%s', '%s', '%d'
            ]
        );

        if ($result) {
            return $this->wpdb->insert_id;
        }
        return false;
    }

    public function update($id, $data) {
        $data['updated_at'] = current_time('mysql');
        
        return $this->wpdb->update(
            $this->table_name,
            $data,
            ['id' => $id],
            null,
            ['%d']
        );
    }

    public function get($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE id = %d",
            $id
        ));
    }

    public function get_by_user($user_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));
    }

    public function get_all() {
        return $this->wpdb->get_results(
            "SELECT * FROM $this->table_name ORDER BY created_at DESC"
        );
    }

    public function get_by_status($status) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE status = %s ORDER BY created_at DESC",
            $status
        ));
    }

    public function get_due_renewals() {
        return $this->wpdb->get_results(
            "SELECT * FROM $this->table_name 
             WHERE status = 'active' 
             AND next_payment <= NOW() 
             ORDER BY next_payment ASC"
        );
    }

    public function update_status($id, $status) {
        return $this->update($id, ['status' => $status]);
    }

    public function suspend($id) {
        return $this->update_status($id, 'suspended');
    }

    public function cancel($id) {
        return $this->update($id, [
            'status' => 'cancelled',
            'end_date' => current_time('mysql')
        ]);
    }

    public function reactivate($id) {
        $subscription = $this->get($id);
        if ($subscription && $subscription->status === 'suspended') {
            $next_payment = $this->calculate_next_payment($subscription);
            return $this->update($id, [
                'status' => 'active',
                'next_payment' => $next_payment
            ]);
        }
        return false;
    }

    public function increment_failed_payments($id) {
        $subscription = $this->get($id);
        if ($subscription) {
            $failed_payments = $subscription->failed_payments + 1;
            $data = ['failed_payments' => $failed_payments];
            
            // 3 başarısız ödemeden sonra askıya al
            if ($failed_payments >= 3) {
                $data['status'] = 'suspended';
            }
            
            return $this->update($id, $data);
        }
        return false;
    }

    public function process_successful_payment($id) {
        $subscription = $this->get($id);
        if ($subscription) {
            $completed_cycles = $subscription->completed_cycles + 1;
            $next_payment = $this->calculate_next_payment($subscription);
            
            $data = [
                'completed_cycles' => $completed_cycles,
                'failed_payments' => 0, // Reset failed payments
                'next_payment' => $next_payment
            ];

            // Billing cycle kontrolü
            if ($subscription->billing_cycles > 0 && $completed_cycles >= $subscription->billing_cycles) {
                $data['status'] = 'completed';
                $data['end_date'] = current_time('mysql');
            }

            return $this->update($id, $data);
        }
        return false;
    }

    private function calculate_next_payment($subscription) {
        $current_date = new \DateTime($subscription->next_payment);
        
        switch ($subscription->period) {
            case 'day':
                $current_date->add(new \DateInterval('P' . $subscription->period_interval . 'D'));
                break;
            case 'week':
                $current_date->add(new \DateInterval('P' . ($subscription->period_interval * 7) . 'D'));
                break;
            case 'month':
                $current_date->add(new \DateInterval('P' . $subscription->period_interval . 'M'));
                break;
            case 'year':
                $current_date->add(new \DateInterval('P' . $subscription->period_interval . 'Y'));
                break;
        }
        
        return $current_date->format('Y-m-d H:i:s');
    }

    public function get_subscription_stats() {
        $stats = $this->wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                SUM(CASE WHEN status = 'active' THEN amount ELSE 0 END) as monthly_revenue
             FROM $this->table_name"
        );
        
        return $stats;
    }

    public function get_revenue_by_period($start_date, $end_date) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                SUM(amount) as revenue,
                COUNT(*) as subscriptions
             FROM $this->table_name 
             WHERE created_at BETWEEN %s AND %s
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $start_date,
            $end_date
        ));
    }
} 