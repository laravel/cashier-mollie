<?php
declare(strict_types=1);

namespace Laravel\Cashier\Refunds;

use Illuminate\Support\Collection;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Money\Money;

class RefundItemCollection extends Collection
{
    public static function makeFromOrderItemCollection(OrderItemCollection $orderItems, array $overrides = []): self
    {
        $refundItems = $orderItems->map(function (OrderItem $orderItem) use ($overrides) {
            return RefundItem::makeFromOrderItem($orderItem, $overrides);
        })->all();

        return new static($refundItems);
    }

    public function getTotal(): Money
    {
        return money($this->sum('total'), $this->getCurrency());
    }

    public function getCurrency(): string
    {
        return $this->first()->currency;
    }
}
