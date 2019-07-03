<?php

namespace Laravel\Cashier\Events;

use Mollie\Api\Resources\Payment;

class FirstPaymentPaid
{
    /**
     * @var \Mollie\Api\Resources\Payment
     */
    public $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }
}
