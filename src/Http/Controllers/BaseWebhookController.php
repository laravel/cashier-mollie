<?php

namespace Laravel\Cashier\Http\Controllers;

use Mollie\Api\Exceptions\ApiException;

abstract class BaseWebhookController
{
    /**
     * Fetch a payment from Mollie using its ID.
     * Returns null if the payment cannot be retrieved.
     *
     * @param $id
     * @return \Mollie\Api\Resources\Payment|null
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getPaymentById($id)
    {
        try {
            return mollie()->payments()->get($id);
        } catch (ApiException $e) {
            if(! config('app.debug')) {
                // Prevent leaking information
                return null;
            }
            throw $e;
        }
    }
}
