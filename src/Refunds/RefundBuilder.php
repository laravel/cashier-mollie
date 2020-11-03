<?php
declare(strict_types=1);

namespace Laravel\Cashier\Refunds;

use Laravel\Cashier\Order\Order;

class RefundBuilder
{
    /**
     * @var \Laravel\Cashier\Order\Order
     */
    protected $order;

    public function __construct(Order $order)
    {

        $this->order = $order;
    }

    public static function forOrder(Order $order): self
    {
        return new static($order);
    }
}
