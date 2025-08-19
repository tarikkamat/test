<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces;

interface PluginServiceInterface
{
    public function loadPluginTextdomain(): void;
    public function createDatabaseTables(): void;
    public function addIyzicoGateway(array $gateways): array;
    public function addWooCommerceBlocksSupport(): void;
}
