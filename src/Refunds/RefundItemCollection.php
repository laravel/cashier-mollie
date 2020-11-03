<?php
declare(strict_types=1);

namespace Laravel\Cashier\Refunds;

use Illuminate\Support\Collection;

class RefundItemCollection extends Collection
{
    public static function fromOrderItemCollection(\Laravel\Cashier\Order\OrderItemCollection $items): self
    {
        return new static(); // TODO
    }
}
