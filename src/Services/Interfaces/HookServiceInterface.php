<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces;

interface HookServiceInterface
{
    public function registerProductHooks(): void;
    public function registerTemplateHooks(): void;
    public function registerAccountHooks(): void;
    public function registerPaymentHooks(): void;
    public function registerAdminHooks(): void;
    public function registerAjaxHooks(): void;
}
