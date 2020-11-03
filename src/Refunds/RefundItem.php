<?php
declare(strict_types=1);

namespace Laravel\Cashier\Refunds;

use Laravel\Cashier\Order\OrderItem;

class RefundItem
{
    public static function makeFromOrderItem(OrderItem $orderItem): self
    {
        return new static; // TODO
    }
}
