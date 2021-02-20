<?php

use Laravel\Cashier\Cashier;

Route::namespace('\Laravel\Cashier\Http\Controllers')->group(function () {
    Route::name('webhooks.mollie.default')->post(
        Cashier::webhookUrl(),
        'WebhookController@handleWebhook'
    );

    Route::name('webhooks.mollie.aftercare')->post(
        Cashier::aftercareWebhookUrl(),
        'AftercareWebhookController@handleWebhook'
    );

    Route::name('webhooks.mollie.first_payment')->post(
        Cashier::firstPaymentWebhookUrl(),
        'FirstPaymentWebhookController@handleWebhook'
    );

    Route::name('webhooks.mollie.one_off_payment')->post(
        Cashier::oneOffPaymentWebhookUrl(),
        'OneOffPaymentWebhookController@handleWebhook'
    );
});
