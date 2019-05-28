<?php

namespace Laravel\Cashier\Events;

use Mollie\Api\Resources\Payment;

class FirstPaymentFailed
{
    /**
     * @var \Mollie\Api\Resources\Payment
     */
    protected $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }
}
