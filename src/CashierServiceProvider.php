<?php

namespace Laravel\Cashier;

use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Console\Commands\CashierInstall;
use Laravel\Cashier\Console\Commands\CashierRun;
use Laravel\Cashier\Coupon\ConfigCouponRepository;
use Laravel\Cashier\Coupon\Contracts\CouponRepository;
use Laravel\Cashier\Mollie\RegistersMollieInteractions;
use Laravel\Cashier\Order\Contracts\MinimumPayment as MinimumPaymentContract;
use Laravel\Cashier\Plan\ConfigPlanRepository;
use Laravel\Cashier\Plan\Contracts\PlanRepository;
use Mollie\Laravel\MollieServiceProvider;

class CashierServiceProvider extends ServiceProvider
{
    use RegistersMollieInteractions;

    const PACKAGE_VERSION = '1.14.0';

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->mergeConfig();
        if (Cashier::$registersRoutes) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        }
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cashier');

        mollie()->addVersionString('MollieLaravelCashier/' . self::PACKAGE_VERSION);

        if ($this->app->runningInConsole()) {
            $this->publishMigrations('cashier-migrations');
            $this->publishConfig('cashier-configs');
            $this->publishViews('cashier-views');
        }

        $this->configureCurrency();
        $this->configureCurrencyLocale();
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->app->register(MollieServiceProvider::class);
        $this->registerMollieInteractions($this->app);
        $this->app->bind(PlanRepository::class, ConfigPlanRepository::class);
        $this->app->singleton(CouponRepository::class, function () {
            return new ConfigCouponRepository(
                config('cashier_coupons.defaults'),
                config('cashier_coupons.coupons')
            );
        });
        $this->app->bind(MinimumPaymentContract::class, MinimumPayment::class);

        $this->commands([
            CashierInstall::class,
            CashierRun::class,
        ]);

        $this->app->register(EventServiceProvider::class);
    }

    protected function mergeConfig()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cashier.php', 'cashier');
        $this->mergeConfigFrom(__DIR__.'/../config/cashier_coupons.php', 'cashier_coupons');
        $this->mergeConfigFrom(__DIR__.'/../config/cashier_plans.php', 'cashier_plans');
    }

    protected function publishMigrations(string $tag)
    {
        if (Cashier::$runsMigrations) {
            $prefix = 'migrations/'.date('Y_m_d_His', time());

            $this->publishes([
                __DIR__.'/../database/migrations/create_applied_coupons_table.php.stub' => database_path($prefix.'_create_applied_coupons_table.php'),
                __DIR__.'/../database/migrations/create_redeemed_coupons_table.php.stub' => database_path($prefix.'_create_redeemed_coupons_table.php'),
                __DIR__.'/../database/migrations/create_credits_table.php.stub' => database_path($prefix.'_create_credits_table.php'),
                __DIR__.'/../database/migrations/create_orders_table.php.stub' => database_path($prefix.'_create_orders_table.php'),
                __DIR__.'/../database/migrations/create_order_items_table.php.stub' => database_path($prefix.'_create_order_items_table.php'),
                __DIR__.'/../database/migrations/create_subscriptions_table.php.stub' => database_path($prefix.'_create_subscriptions_table.php'),
            ], $tag);
        }
    }

    protected function publishConfig(string $tag)
    {
        $this->publishes([
            __DIR__.'/../config/cashier.php' => config_path('cashier.php'),
            __DIR__.'/../config/cashier_coupons.php' => config_path('cashier_coupons.php'),
            __DIR__.'/../config/cashier_plans.php' => config_path('cashier_plans.php'),
        ], $tag);
    }

    protected function publishViews(string $tag)
    {
        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->basePath('resources/views/vendor/cashier'),
        ], $tag);
    }

    protected function configureCurrency()
    {
        $currency = config('cashier.currency', false);
        if ($currency) {
            Cashier::useCurrency($currency);
        }
    }

    protected function configureCurrencyLocale()
    {
        $locale = config('cashier.currency_locale', false);
        if ($locale) {
            Cashier::useCurrencyLocale($locale);
        }
    }
}
