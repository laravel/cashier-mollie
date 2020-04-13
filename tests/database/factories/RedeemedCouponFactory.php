<?php

namespace Laravel\Cashier\Database\Factories;

use Faker\Generator as Faker;
use Laravel\Cashier\Coupon\RedeemedCoupon;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\Fixtures\User;

$factory->define(RedeemedCoupon::class, function (Faker $faker) {
    $user = new User();
    return [
        'name' => 'Test redemeed coupon',
        'model_type' => (new Subscription())->getMorphClass(),
        'model_id' => 1,
        'owner_type' => (new User())->getMorphClass(),
        'owner_id' => 1,
        'times_left' => 1,
    ];
});
