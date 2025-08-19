<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Models;

use Iyzico\IyzipayWoocommerceSubscription\Services\SubscriptionService;
use Iyzico\IyzipayWoocommerceSubscription\Services\TemplateService;
use Iyzico\IyzipayWoocommerceSubscription\Services\EmailService;
use Iyzico\IyzipayWoocommerceSubscription\Services\RenewalService;
use Iyzico\IyzipayWoocommerceSubscription\Services\SubscriptionAdminService;
use Iyzico\IyzipayWoocommerceSubscription\Services\ProductService;
use Iyzico\IyzipayWoocommerceSubscription\Services\AccountService;
use Iyzico\IyzipayWoocommerceSubscription\Services\PluginService;
use Iyzico\IyzipayWoocommerceSubscription\Services\HookService;
use Iyzico\IyzipayWoocommerceSubscription\Gateway\IyzicoGateway;

class SubscriptionFactory
{
    public static function createSubscriptionService(): SubscriptionService
    {
        $validator = new SubscriptionValidator();
        $calculator = new SubscriptionCalculator();
        $repository = new SubscriptionRepository($validator, $calculator);
        
        return new SubscriptionService($repository, $calculator);
    }

    public static function createSubscriptionRepository(): SubscriptionRepository
    {
        $validator = new SubscriptionValidator();
        $calculator = new SubscriptionCalculator();
        
        return new SubscriptionRepository($validator, $calculator);
    }

    public static function createSubscriptionValidator(): SubscriptionValidator
    {
        return new SubscriptionValidator();
    }

    public static function createSubscriptionCalculator(): SubscriptionCalculator
    {
        return new SubscriptionCalculator();
    }

    public static function createTemplateService(): TemplateService
    {
        return new TemplateService();
    }

    public static function createEmailTemplateService(): TemplateService
    {
        return new TemplateService();
    }

    public static function createEmailService(): EmailService
    {
        $templateService = self::createEmailTemplateService();
        return new EmailService($templateService);
    }

    public static function createRenewalService(): RenewalService
    {
        $repository = self::createSubscriptionRepository();
        $gateway = new IyzicoGateway();
        $emailService = self::createEmailService();
        
        return new RenewalService($repository, $gateway, $emailService);
    }

    public static function createSubscriptionAdminService(): SubscriptionAdminService
    {
        $repository = self::createSubscriptionRepository();
        return new SubscriptionAdminService($repository);
    }

    public static function createProductService(): ProductService
    {
        return new ProductService();
    }

    public static function createAccountService(): AccountService
    {
        $repository = self::createSubscriptionRepository();
        return new AccountService($repository);
    }

    public static function createPluginService(): PluginService
    {
        return new PluginService();
    }

    public static function createHookService(): HookService
    {
        $productService = self::createProductService();
        $templateService = self::createTemplateService();
        $accountService = self::createAccountService();
        $pluginService = self::createPluginService();
        $renewalService = self::createRenewalService();
        
        return new HookService($productService, $templateService, $accountService, $pluginService, $renewalService);
    }
}
