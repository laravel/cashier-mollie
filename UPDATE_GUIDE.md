# Upgrading Cashier Mollie to v2

## Publish and run the migrations

    php artisan cashier:update

## Update your config files

In your `cashier.php` config file, add the `aftercare_webhook_url` below the `webhook_url`. 

    /**
     * The default webhook url is called by Mollie on payment status updates. You can use either a relative or
     * absolute url.
     */
    'webhook_url' => 'webhooks/mollie',
    
    /**
     * The default aftercare webhook url is called by Mollie on refunds and chargebacks. You can use either a relative or
     * absolute url.
     */
    'aftercare_webhook_url' => 'webhooks/mollie/aftercare',
