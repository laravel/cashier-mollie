<?php

namespace Laravel\Cashier\Order\Contracts;

use Laravel\Cashier\Order\OrderItem;

interface PreprocessesOrderItems
{
    /**
     * Called right before processing the order item into an order.
     *
     * @param OrderItem $item
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public static function preprocessOrderItem(OrderItem $item);
}
