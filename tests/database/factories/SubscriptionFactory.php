<?php

namespace Laravel\Cashier\Database\Factories;

use Carbon\Carbon;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\Fixtures\User;

$factory->define(Subscription::class, function (Faker $faker) {
    return [
        'name' => 'dummy name',
        'plan' => 'monthly-10-1',
        'owner_id' => 1,
        'owner_type' => Model::getActualClassNameForMorph(User::class),
        'cycle_started_at' => now(),
        'cycle_ends_at' => function (array $subscription) {
            return Carbon::parse($subscription['cycle_started_at'])->addMonth();
        },
        'tax_percentage' => 0,
    ];
});
