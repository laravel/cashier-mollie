<?php

namespace Laravel\Cashier\SubscriptionBuilder;

use Illuminate\Http\RedirectResponse;
use Mollie\Api\Resources\Payment;

class RedirectToCheckoutResponse extends RedirectResponse
{
    /** @var \Mollie\Api\Resources\Payment */
    protected $payment;

    /**
     * @param \Mollie\Api\Resources\Payment $payment
     * @return \Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse
     */
    public static function forPayment(Payment $payment)
    {
        $response = new static($payment->getCheckoutUrl());

        return $response->setPayment($payment);
    }

    /**
     * @return \Mollie\Api\Resources\Payment
     */
    public function payment()
    {
        return $this->payment;
    }

    /**
     * @param \Mollie\Api\Resources\Payment $payment
     * @return $this
     */
    protected function setPayment(Payment $payment)
    {
        $this->payment = $payment;

        return $this;
    }
}
