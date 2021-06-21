<?php

namespace Laravel\Cashier\Database\Factories;

use Faker\Generator as Faker;
use Laravel\Cashier\Refunds\Refund;
use Laravel\Cashier\Tests\Fixtures\User;
use Mollie\Api\Types\RefundStatus;

$factory->define(Refund::class, function (Faker $faker) {
    return [
        'owner_id' => 1,
        'owner_type' => User::class,
        'original_order_id' => 1,
        'mollie_refund_id' => 're_dummy_refund_id',
        'mollie_refund_status' => RefundStatus::STATUS_PENDING,
    ];
});
