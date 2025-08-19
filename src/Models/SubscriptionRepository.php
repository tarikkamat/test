<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Models;

use Iyzico\IyzipayWoocommerceSubscription\Models\Interfaces\SubscriptionRepositoryInterface;
use Iyzico\IyzipayWoocommerceSubscription\Models\Interfaces\SubscriptionValidatorInterface;
use Iyzico\IyzipayWoocommerceSubscription\Models\Interfaces\SubscriptionCalculatorInterface;

class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    private $wpdb;
    private $table_name;
    private $validator;
    private $calculator;

    public function __construct(
        SubscriptionValidatorInterface $validator,
        SubscriptionCalculatorInterface $calculator
    ) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'iyzico_subscriptions';
        $this->validator = $validator;
        $this->calculator = $calculator;
    }

    public function create(array $data): ?int
    {
        $validated_data = $this->validator->validateCreateData($data);
        
        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'user_id' => $validated_data['user_id'],
                'order_id' => $validated_data['order_id'],
                'product_id' => $validated_data['product_id'],
                'iyzico_subscription_id' => $validated_data['iyzico_subscription_id'],
                'status' => $validated_data['status'] ?? 'pending',
                'amount' => $validated_data['amount'],
                'currency' => $validated_data['currency'] ?? 'TRY',
                'period' => $validated_data['period'],
                'period_interval' => $validated_data['period_interval'] ?? 1,
                'start_date' => $validated_data['start_date'],
                'next_payment' => $validated_data['next_payment'],
                'end_date' => $validated_data['end_date'] ?? null,
                'trial_end_date' => $validated_data['trial_end_date'] ?? null,
                'payment_method' => $validated_data['payment_method'],
                'billing_cycles' => $validated_data['billing_cycles'] ?? 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            [
                '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%d',
                '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'
            ]
        );

        if ($result) {
            return $this->wpdb->insert_id;
        }
        return null;
    }

    public function update(int $id, array $data): bool
    {
        error_log("update çağrıldı: ID=$id, Data=" . print_r($data, true));
        
        $validated_data = $this->validator->validateUpdateData($data);
        error_log("Validated data: " . print_r($validated_data, true));
        
        $validated_data['updated_at'] = current_time('mysql');
        
        $result = $this->wpdb->update(
            $this->table_name,
            $validated_data,
            ['id' => $id],
            null,
            ['%d']
        );
        
        error_log("update sonucu: " . ($result ? 'başarılı' : 'başarısız'));
        if (!$result) {
            error_log("WP_Error: " . $this->wpdb->last_error);
        }
        
        return $result;
    }

    public function find(int $id): ?object
    {
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE id = %d",
            $id
        ));
        
        return $result ?: null;
    }

    public function findByUser(int $user_id): array
    {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));
    }

    public function findAll(): array
    {
        return $this->wpdb->get_results(
            "SELECT * FROM $this->table_name ORDER BY created_at DESC"
        );
    }

    public function findByStatus(string $status): array
    {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE status = %s ORDER BY created_at DESC",
            $status
        ));
    }

    public function findByFilters(array $filters): array
    {
        $where = [];
        $params = [];
        $join_users = false;

        // Status filter
        if (!empty($filters['status'])) {
            $where[] = 's.status = %s';
            $params[] = $filters['status'];
        }

        // Customer search (display name or email)
        if (!empty($filters['customer_search'])) {
            $join_users = true;
            $where[] = '(u.display_name LIKE %s OR u.user_email LIKE %s)';
            $like = '%' . $this->wpdb->esc_like($filters['customer_search']) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        // Date range filters (by start_date)
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(s.start_date) >= %s';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(s.start_date) <= %s';
            $params[] = $filters['date_to'];
        }

        $where_sql = '';
        if (!empty($where)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where);
        }

        $users_table = $this->wpdb->users;
        $join_sql = $join_users ? "INNER JOIN $users_table u ON u.ID = s.user_id" : '';

        $sql = "SELECT s.* FROM $this->table_name s $join_sql $where_sql ORDER BY s.created_at DESC";

        if (!empty($params)) {
            // Prepare with parameters
            $prepared = $this->wpdb->prepare($sql, $params);
            return $this->wpdb->get_results($prepared);
        }

        return $this->wpdb->get_results($sql);
    }

    public function findDueRenewals(): array
    {
        return $this->wpdb->get_results(
            "SELECT * FROM $this->table_name 
             WHERE status = 'active' 
             AND next_payment <= NOW() 
             ORDER BY next_payment ASC"
        );
    }

    public function updateStatus(int $id, string $status): bool
    {
        error_log("updateStatus çağrıldı: ID=$id, Status=$status");
        $result = $this->update($id, ['status' => $status]);
        error_log("updateStatus sonucu: " . ($result ? 'başarılı' : 'başarısız'));
        return $result;
    }

    public function suspend(int $id): bool
    {
        return $this->updateStatus($id, 'suspended');
    }

    public function cancel(int $id): bool
    {
        return $this->update($id, [
            'status' => 'cancelled',
            'end_date' => current_time('mysql')
        ]);
    }

    public function reactivate(int $id): bool
    {
        $subscription = $this->find($id);
        if ($subscription && $subscription->status === 'suspended') {
            $next_payment = $this->calculator->calculateNextPayment($subscription);
            return $this->update($id, [
                'status' => 'active',
                'next_payment' => $next_payment
            ]);
        }
        return false;
    }

    public function incrementFailedPayments(int $id): bool
    {
        $subscription = $this->find($id);
        if ($subscription) {
            $failed_payments = ($subscription->failed_payments ?? 0) + 1;
            $data = ['failed_payments' => $failed_payments];
            
            // 3 başarısız ödemeden sonra askıya al
            if ($failed_payments >= 3) {
                $data['status'] = 'suspended';
            }
            
            return $this->update($id, $data);
        }
        return false;
    }

    public function processSuccessfulPayment(int $id): bool
    {
        $subscription = $this->find($id);
        if ($subscription) {
            $completed_cycles = ($subscription->completed_cycles ?? 0) + 1;
            $next_payment = $this->calculator->calculateNextPayment($subscription);
            
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

    public function getSubscriptionStats(): object
    {
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

    public function getRevenueByPeriod(string $start_date, string $end_date): array
    {
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
