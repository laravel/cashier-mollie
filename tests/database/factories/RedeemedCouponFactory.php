<?php

namespace Laravel\Cashier\Database\Factories;

use Faker\Generator as Faker;
use Laravel\Cashier\Coupon\RedeemedCoupon;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\Fixtures\User;

$factory->define(RedeemedCoupon::class, function (Faker $faker) {
    return [
        'id' => 1,
        'name' => 'Test redemeed coupon',
        'model_type' => Subscription::class,
        'model_id' => 1,
        'owner_type' => User::class,
        'owner_id' => 1,
        'times_left' => 1,
    ];
});
