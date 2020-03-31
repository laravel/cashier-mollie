<?php

namespace Laravel\Cashier\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use Mollie\Api\Resources\Payment;

class MandateUpdated
{
    use SerializesModels;

    /** @var \Illuminate\Database\Eloquent\Model */
    public $owner;

    /** @var \Mollie\Api\Resources\Payment */
    public $payment;

    /**
     * MandateUpdated constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     */
    public function __construct(Model $owner, Payment $payment)
    {
        $this->owner = $owner;
        $this->payment = $payment;
    }
}
