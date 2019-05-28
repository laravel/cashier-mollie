<?php

namespace Laravel\Cashier\Events;

use Illuminate\Queue\SerializesModels;

class OrderPaymentPaid
{
    use SerializesModels;

    /**
     * The paid order.
     *
     * @var Order
     */
    public $order;

    /**
     * Creates a new OrderPaymentPaid event.
     *
     * @param $order
     */
    public function __construct($order)
    {
        $this->order = $order;
    }
}
