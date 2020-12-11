<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie;

use Illuminate\Contracts\Foundation\Application;

trait RegistersMollieInteractions
{
    protected function registerMollieInteractions(Application $app)
    {
        $interactions = collect([
            Contracts\CreateMollieCustomer::class => CreateMollieCustomer::class,
            Contracts\CreateMolliePayment::class => CreateMolliePayment::class,
            Contracts\GetMollieCustomer::class => GetMollieCustomer::class,
            Contracts\GetMollieMandate::class => GetMollieMandate::class,
            Contracts\GetMolliePayment::class => GetMolliePayment::class,
            Contracts\GetMollieMethodMinimumAmount::class => GetMollieMethodMinimumAmount::class,
            Contracts\UpdateMolliePayment::class => UpdateMolliePayment::class,
        ]);

        $interactions->each(function ($concrete, $abstract) use ($app) {
            $app->bind($abstract, $concrete);
        });
    }
}
