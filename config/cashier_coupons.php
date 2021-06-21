<?php

return [

    /** Settings applied to every coupon. Can be overridden per coupon. */
    'defaults' => [

        /**
         * The class responsible for validating and applying the coupon discount.
         * Must extend \Cashier\Discount\BaseCouponHandler
         */
        //'handler' => '\SomeHandler',

        /**
         * The number of times this coupon will be applied. I.e. If you'd like to prove 6 months discount on a
         * monthly subscription:
         *
         * @example 6
         */
        'times' => 1,

        /** Any context you want to pass to the handler */
        'context' => [],
    ],

    /** Available coupons */
    'coupons' => [

        /** The coupon code. Must be unique (case insensitive). */
        'welcome' => [

            /**
             * The class responsible for validating and applying the coupon discount.
             * Must extend \Cashier\Discount\BaseCouponHandler
             */
            'handler' => \Laravel\Cashier\Coupon\FixedDiscountHandler::class,

            /** Any context you want to pass to the handler */
            'context' => [
                'description' => 'Welcome to ' . config('app.name'),
                'discount' => [
                    'currency' => 'EUR', // Make sure the currency matches the subscription plan it's being applied to
                    'value' => '5.00',
                ],

                /** Add credit to the customer's balance if discount results in a negative amount. */
                'allow_surplus' => false,
            ],
        ],

        /** The coupon code. Must be unique (case insensitive). */
        'bonus' => [

            /**
             * The class responsible for validating and applying the coupon discount.
             * Must extend \Cashier\Discount\BaseCouponHandler
             */
            'handler' => \Laravel\Cashier\Coupon\PercentageDiscountHandler::class,

            /** Any context you want to pass to the handler */
            'context' => [
                'description' => 'Extra Bonus ' . config('app.name'),
                'percentage' => '10',
            ],
        ],
    ],

];
