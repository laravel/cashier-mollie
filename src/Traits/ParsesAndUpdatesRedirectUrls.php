<?php

namespace Laravel\Cashier\Traits;

use Illuminate\Support\Str;
use Mollie\Api\Resources\Payment;

trait ParsesAndUpdatesRedirectUrls
{
    /**
     * Replace any placeholders in the payment redirect url
     *
     * @param \Mollie\Api\Resources\Payment $payment
     * @param string $redirectUrl
     * @return \Mollie\Api\Resources\Payment
     */
    public function parseAndUpdateRedirectUrl(Payment $payment, $redirectUrl)
    {
        if (!Str::contains($redirectUrl, '{payment_id}')) {
            return $payment;
        }

        $payment->redirectUrl = Str::replaceArray('{payment_id}', [$payment->id], $redirectUrl);

        return $payment->update();
    }
}
