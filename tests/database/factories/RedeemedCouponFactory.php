<?php

namespace Laravel\Cashier\Database\Factories;

use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Coupon\RedeemedCoupon;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\Fixtures\User;

$factory->define(RedeemedCoupon::class, function (Faker $faker) {
    return [
        'name' => 'Test redemeed coupon',
        'model_type' => Model::getActualClassNameForMorph(Subscription::class),
        'model_id' => 1,
        'owner_type' => Model::getActualClassNameForMorph(User::class),
        'owner_id' => 1,
        'times_left' => 1,
    ];
});
