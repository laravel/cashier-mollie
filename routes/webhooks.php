<?php

Route::namespace('\Laravel\Cashier\Http\Controllers')->group(function () {

    Route::name('webhooks.mollie.default')->post(
        'webhooks/mollie',
        'WebhookController@handleWebhook'
    );

    Route::name('webhooks.mollie.first_payment')->post(
        'webhooks/mollie/first-payment',
        'FirstPaymentWebhookController@handleWebhook'
    );

    Route::name('webhooks.mollie.one_off_payment')->post(
        'webhooks/mollie/one-off-payment',
        'OneOffPaymentWebhookController@handleWebhook'
    );

});
