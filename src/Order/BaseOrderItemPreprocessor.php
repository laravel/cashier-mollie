<?php

namespace Laravel\Cashier\Order;

abstract class BaseOrderItemPreprocessor
{
    /**
     * @param \Laravel\Cashier\Order\OrderItemCollection $items
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    abstract public function handle(OrderItemCollection $items);
}
