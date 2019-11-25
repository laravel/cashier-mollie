<?php

namespace Laravel\Cashier\Events;

use Illuminate\Queue\SerializesModels;
use Mollie\Api\Resources\Payment;

class FirstPaymentFailed
{
    use SerializesModels;

    /**
     * @var \Mollie\Api\Resources\Payment
     */
    public $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }
}
