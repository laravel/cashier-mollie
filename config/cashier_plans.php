<?php

use Laravel\Cashier\Coupon\CouponOrderItemPreprocessor as ProcessCoupons;
use Laravel\Cashier\Order\PersistOrderItemsPreprocessor as PersistOrderItems;
use Laravel\Cashier\Plan\AdvancedIntervalGenerator;

return [

    /**
     * Plan defaults. Can be overridden per Plan.
     */
    'defaults' => [

        /**
         * A first payment requires a customer to go through the Mollie checkout screen in order to create a Mandate for
         * future recurring payments.
         * @link https://docs.mollie.com/payments/recurring#payments-recurring-first-payment
         */
        'first_payment' => config('cashier.first_payment'),

        /**
         * The default chain of subscription OrderItem preprocessors. These are called right before the Subscription's
         * OrderItem is processed into an OrderItem. You can use this for calculating variable costs a.k.a. metered billing.
         * Can be overridden per subscription plan. Make sure the preprocessors implement the PreprocessesOrderItems
         * interface.
         */
         'order_item_preprocessors' => [
             ProcessCoupons::class,
             PersistOrderItems::class,
         ],
    ],

    /**
     * The subscription plans.
     */
    'plans' => [

        /**
         * The plan reference.
         */
        'example-1' => [

            /**
             * The amount to be billed each billing cycle.
             */
            'amount' => [

                /**
                 * A string containing the exact amount you want to charge each billing cycle, in the given currency.
                 * Make sure to set the right amount of decimals. Non-string values are not accepted by Mollie.
                 */
                'value' => '10.00',

                /**
                 * An ISO 4217 currency code. The currencies supported depend on the payment methods that are enabled on
                 * your Mollie account.
                 */
                'currency' => 'EUR',
            ],

            /**
             * The length of the billing cycle.
             */
            // 'interval' => '1 month' or
            'interval' => [
                'generator' => AdvancedIntervalGenerator::class,
                'value' => 1,
                'period' => 'month', /* day, month or year*/
                'monthOverflow' => true,
            ],

            /**
             * The text to appear on the invoice.
             */
            'description' => 'Monthly payment',

            /**
             * The chain of subscription OrderItem preprocessors. These are called right before the Subscription's
             * OrderItem is processed into an OrderItem. You can use this for calculating variable costs a.k.a. metered
             * billing. Make sure the preprocessors extend the BaseOrderItemProcessor.
             */
            //'order_item_preprocessors' => [
            //    ProcessCoupons::class,
            //    PersistOrderItems::class,
            //],
        ],
    ],
];
