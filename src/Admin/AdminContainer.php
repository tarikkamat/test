<?php

namespace Iyzico\IyzipayWoocommerceSubscription\Admin;

use Iyzico\IyzipayWoocommerceSubscription\Admin\Views\SubscriptionAdminView;
use Iyzico\IyzipayWoocommerceSubscription\Admin\Views\SubscriptionTableRenderer;
use Iyzico\IyzipayWoocommerceSubscription\Admin\Views\SubscriptionStatsRenderer;
use Iyzico\IyzipayWoocommerceSubscription\Admin\Views\SubscriptionFiltersRenderer;
use Iyzico\IyzipayWoocommerceSubscription\Services\SubscriptionAdminService;
use Iyzico\IyzipayWoocommerceSubscription\Services\RenewalService;
use Iyzico\IyzipayWoocommerceSubscription\Models\SubscriptionFactory;

class AdminContainer {
    private static array $instances = [];

    public static function get_subscription_admin_controller(): SubscriptionAdminController {
        if (!isset(self::$instances['subscription_admin_controller'])) {
            self::$instances['subscription_admin_controller'] = new SubscriptionAdminController(
                self::get_subscription_admin_view(),
                self::get_subscription_admin_service(),
                self::get_renewal_service()
            );
        }
        
        return self::$instances['subscription_admin_controller'];
    }

    private static function get_subscription_admin_view(): SubscriptionAdminView {
        if (!isset(self::$instances['subscription_admin_view'])) {
            self::$instances['subscription_admin_view'] = new SubscriptionAdminView(
                self::get_subscription_admin_service(),
                self::get_subscription_table_renderer(),
                self::get_subscription_stats_renderer(),
                self::get_subscription_filters_renderer()
            );
        }
        
        return self::$instances['subscription_admin_view'];
    }

    private static function get_subscription_admin_service(): SubscriptionAdminService {
        if (!isset(self::$instances['subscription_admin_service'])) {
            self::$instances['subscription_admin_service'] = SubscriptionFactory::createSubscriptionAdminService();
        }
        
        return self::$instances['subscription_admin_service'];
    }

    private static function get_subscription_table_renderer(): SubscriptionTableRenderer {
        if (!isset(self::$instances['subscription_table_renderer'])) {
            self::$instances['subscription_table_renderer'] = new SubscriptionTableRenderer();
        }
        
        return self::$instances['subscription_table_renderer'];
    }

    private static function get_subscription_stats_renderer(): SubscriptionStatsRenderer {
        if (!isset(self::$instances['subscription_stats_renderer'])) {
            self::$instances['subscription_stats_renderer'] = new SubscriptionStatsRenderer();
        }
        
        return self::$instances['subscription_stats_renderer'];
    }

    private static function get_subscription_filters_renderer(): SubscriptionFiltersRenderer {
        if (!isset(self::$instances['subscription_filters_renderer'])) {
            self::$instances['subscription_filters_renderer'] = new SubscriptionFiltersRenderer();
        }
        
        return self::$instances['subscription_filters_renderer'];
    }

    private static function get_renewal_service(): RenewalService {
        if (!isset(self::$instances['renewal_service'])) {
            self::$instances['renewal_service'] = SubscriptionFactory::createRenewalService();
        }
        
        return self::$instances['renewal_service'];
    }

    public static function clear_instances(): void {
        self::$instances = [];
    }
}
