<?php

namespace Laravel\Cashier;

use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Console\Commands\CashierInstall;
use Laravel\Cashier\Console\Commands\CashierRun;
use Laravel\Cashier\Order\Contracts\MinimumPayment as MinimumPaymentContract;
use Laravel\Cashier\Coupon\ConfigCouponRepository;
use Laravel\Cashier\Coupon\Contracts\CouponRepository;
use Laravel\Cashier\Plan\ConfigPlanRepository;
use Laravel\Cashier\Plan\Contracts\PlanRepository;
use Mollie\Laravel\MollieServiceProvider;

class CashierServiceProvider extends ServiceProvider
{
    const PACKAGE_VERSION = '1.0.0';

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        $this->mergeConfig();
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cashier');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        mollie()->addVersionString('MollieLaravelCashier/' . self::PACKAGE_VERSION);

        if ($this->app->runningInConsole()) {
            $this->publishMigrations('cashier-migrations');
            $this->publishConfig('cashier-configs');
            $this->publishViews('cashier-views');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->app->register(MollieServiceProvider::class);
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
    }

    protected function mergeConfig()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cashier.php', 'cashier');
        $this->mergeConfigFrom(__DIR__.'/../config/cashier_coupons.php', 'cashier_coupons');
        $this->mergeConfigFrom(__DIR__.'/../config/cashier_plans.php', 'cashier_plans');
    }

    protected function publishMigrations(string $tag)
    {
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations')
        ], $tag);
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
}
