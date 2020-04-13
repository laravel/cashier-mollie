<?php

namespace Laravel\Cashier\Database\Factories;

use Faker\Generator as Faker;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Tests\Fixtures\User;

$factory->define(Order::class, function (Faker $faker) {
    return [
        'owner_id' => 1,
        'owner_type' => (new User())->getMorphClass(),
        'currency' => 'EUR',
        'subtotal' => 123,
        'tax' => 0,
        'total' => 123,
        'total_due' => 123,
        'number' => '2018-0000-0001',
        'credit_used' => 0,
        'balance_before' => 0,
    ];
});
