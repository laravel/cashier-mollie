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

});
