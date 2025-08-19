<?php

namespace Iyzico\IyzipayWoocommerceSubscription;

use Iyzico\IyzipayWoocommerceSubscription\Services\Interfaces\HookServiceInterface;

class Plugin {
    private static $instance = null;
    private static $container = null;
    
    // Hook service instance
    private HookServiceInterface $hookService;

    public function __construct() {
        if (self::$instance !== null) {
            throw new \Exception('Plugin sınıfı singleton olmalıdır.');
        }
        self::$instance = $this;
    }

    /**
     * Statik init metodu - ana dosyadan çağrılır
     */
    public static function init($container = null): void
    {
        if ($container) {
            self::$container = $container;
        }
        
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        self::$instance->initInstance();
    }

    /**
     * Container'a erişim için statik metod
     */
    public static function container()
    {
        return self::$container;
    }

    /**
     * Instance init metodu - eski init metodunun yerine
     */
    public function initInstance(): void
    {

        $this->initHookService();
        
        // Tüm hook'ları kaydet
        $this->hookService->registerPluginHooks();
        $this->hookService->registerProductHooks();
        $this->hookService->registerTemplateHooks();
        $this->hookService->registerAccountHooks();
        $this->hookService->registerPaymentHooks();
        $this->hookService->registerAdminHooks();
        $this->hookService->registerAjaxHooks();
    }

    private function initHookService(): void
    {
        $this->hookService = \Iyzico\IyzipayWoocommerceSubscription\Models\SubscriptionFactory::createHookService();
    }
} 