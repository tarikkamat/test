<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces;

interface AccountServiceInterface
{
    public function addAccountEndpoints(): void;
    public function addAccountMenuItems(array $items): array;
    public function renderSubscriptionsAccountPage(): void;
    public function getStatusLabel(string $status): string;
    public function renderSubscriptionActions(object $subscription): void;
}
