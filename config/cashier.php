<?php

return [

    /**
     * The default webhook url is called by Mollie on payment status updates. You can use either a relative or
     * absolute url.
     */
    'webhook_url' => 'webhooks/mollie',

    /**
     * The default locale passed to Mollie for configuring the checkout screen. Set to null to let Mollie handle it for
     * you.
     * @link https://docs.mollie.com/reference/v2/payments-api/create-payment#parameters
     * @example 'nl_NL'
     */
    'locale' => null,

    /**
     * Used for generating Order numbers, used in Orders and related Invoices.
     */
    'order_number_generator' => [

        /**
         * The model used for Order Numbers. You can extend and override it here to implement your own logic.
         */
        'model' => \Laravel\Cashier\Order\OrderNumberGenerator::class,

        /**
         * The offset used by the Order Number Generator.
         */
        'offset' => 0,
    ],

    /**
     * A first payment requires a customer to go through the Mollie checkout screen in order to create a Mandate for
     * future recurring payments.
     * @link https://docs.mollie.com/payments/recurring#payments-recurring-first-payment
     */
    'first_payment' => [

        /**
         * The first payment webhook url is called by Mollie on first payment status updates. Can be overridden per
         * Plan. You can use either a relative or absolute url.
         */
        'webhook_url' => 'webhooks/mollie/first-payment',

        /**
         * A comma-separated list of allowed Mollie payment methods for the first payment. Make sure the methods are
         * enabled in the Mollie dashboard. Set to NULL to let Mollie handle this for you. Can be overridden per plan.
         * @example 'ideal,creditcard'
         */
        'method' => null,

        /**
         * The default url the customer is redirected to after the Mollie first payment checkout screen. Can be
         * overridden per Plan. You can use a `{payment_id}` placeholder here to easily retrieve the Mollie payment in
         * your controller. Make sure you have set up a matching route.
         */
        'redirect_url' => config('app.url'),

        /**
         * The default amount for a first payment. Can be overridden per Plan.
         */
        'amount' => [

            /**
             * A string containing the exact amount you want to charge for the first payment, in the given currency.
             * Make sure to set the right amount of decimals. Non-string values are not accepted by Mollie.
             */
            'value' => '1.00',

            /**
             * An ISO 4217 currency code. The currencies supported depend on the payment methods that are enabled on
             * your Mollie account.
             */
            'currency' => 'EUR',
        ],

        /**
         * The default description for the first payment, visible on both the invoice and the customer bank records.
         */
        'description' => 'Welcome to ' . config('app.name'),
    ],

    /**
     * A one off payment may require a customer to go through the Mollie checkout screen,
     * but only if they don't have a valid mandate.
     */
    'one_off_payment' => [

        /**
         * The one off payment webhook url is called by Mollie on one off payment status updates.
         * Can be overridden per invoice. You can use either a relative or absolute url.
         * To override this webhook url on invoice level add the 'webhookUrl' option.
         */
        'webhook_url' => 'webhooks/mollie/one-off-payment',

        /**
         * The default url the customer is redirected to after the Mollie one off payment checkout screen. Can be
         * overridden per invoice. You can use a `{payment_id}` placeholder here to easily retrieve the Mollie payment
         * in your controller. Make sure you have set up a matching route.
         */
        'redirect_url' => config('app.url'),

        /**
         * If you wish to limit the payment methods your customers can use, you may
         * fill the array with \Mollie\Api\Types\PaymentMethod you wish to allow.
         * Leaving it as an empty array defaults to all payment methods.
         * @see \Mollie\Api\Types\PaymentMethod
         */
        'active_payment_methods' => [],

        /**
         * The default description for a one off payment, visible on both the invoice and the customer bank records.
         */
        'description' => 'Welcome to ' . config('app.name'),
    ]
];
