<?php

namespace Laravel\Cashier\Events;

use Illuminate\Queue\SerializesModels;

class OrderInvoiceAvailable
{
    use SerializesModels;

    /**
     * The created order.
     *
     * @var \Laravel\Cashier\Order\Order
     */
    public $order;

    /**
     * Creates a new OrderInvoiceAvailable event.
     *
     * @param $order
     */
    public function __construct($order)
    {
        $this->order = $order;
    }
}
