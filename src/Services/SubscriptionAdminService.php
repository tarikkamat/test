<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Services;

use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\SubscriptionAdminServiceInterface;
use Iyzico\IyzipayWoocommerceSubscription\Models\Interfaces\SubscriptionRepositoryInterface;

class SubscriptionAdminService implements SubscriptionAdminServiceInterface
{
    private SubscriptionRepositoryInterface $subscriptionRepository;

    public function __construct(SubscriptionRepositoryInterface $subscriptionRepository) {
        $this->subscriptionRepository = $subscriptionRepository;
    }

    public function performAction(int $subscription_id, string $action): bool {
        error_log("performAction çağrıldı: ID=$subscription_id, Action=$action");
        
        $subscription = $this->subscriptionRepository->find($subscription_id);
        error_log("Subscription bulundu: " . ($subscription ? 'evet' : 'hayır'));

        if (!$subscription) {
            error_log("Subscription bulunamadı: ID=$subscription_id");
            return false;
        }

        error_log("Subscription durumu: " . $subscription->status);

        switch ($action) {
            case 'suspend':
                $result = $this->subscriptionRepository->updateStatus($subscription_id, 'suspended');
                error_log("Suspend sonucu: " . ($result ? 'başarılı' : 'başarısız'));
                return $result;
            case 'cancel':
                $result = $this->subscriptionRepository->updateStatus($subscription_id, 'cancelled');
                error_log("Cancel sonucu: " . ($result ? 'başarılı' : 'başarısız'));
                return $result;
            case 'reactivate':
                $result = $this->subscriptionRepository->updateStatus($subscription_id, 'active');
                error_log("Reactivate sonucu: " . ($result ? 'başarılı' : 'başarısız'));
                return $result;
            default:
                error_log("Bilinmeyen action: $action");
                return false;
        }
    }

    public function getSubscriptionsData(array $filters = []): array {
        $subscriptions = $this->subscriptionRepository->findAll();
        $stats = $this->subscriptionRepository->getSubscriptionStats();

        // Filtreleme
        if (!empty($filters['status'])) {
            $subscriptions = $this->subscriptionRepository->findByStatus($filters['status']);
        }

        return [
            'subscriptions' => $subscriptions,
            'stats' => $stats,
            'filters' => $filters
        ];
    }

    public function getSubscriptionById(int $subscription_id): ?object {
        return $this->subscriptionRepository->find($subscription_id);
    }
}
