<?php

namespace Laravel\Cashier\Database\Factories;

use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\Fixtures\User;

$factory->define(OrderItem::class, function (Faker $faker) {
    return [
        'owner_type' => Model::getActualClassNameForMorph(User::class),
        'owner_id' => 1,
        'orderable_type' => Model::getActualClassNameForMorph(Subscription::class),
        'orderable_id' => 1,
        'description' => 'Some dummy description',
        'unit_price' => 12150,
        'quantity' => 1,
        'tax_percentage' => 21.5,
        'currency' => 'EUR',
        'process_at' => now()->subMinute(),
    ];
});

$factory->state(OrderItem::class, 'unlinked', [
    'orderable_type' => null,
    'orderable_id' => null,
]);

$factory->state(OrderItem::class, 'unprocessed', [
    'order_id' => null,
]);

$factory->state(OrderItem::class, 'processed', [
    'order_id' => 1,
]);

$factory->state(OrderItem::class, 'EUR', [
    'currency' => 'EUR',
]);

$factory->state(OrderItem::class, 'USD', [
    'currency' => 'USD',
]);
