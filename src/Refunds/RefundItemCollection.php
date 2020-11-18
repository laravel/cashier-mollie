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

    public function toNewOrderItemCollection(): OrderItemCollection
    {
        return new OrderItemCollection(
            $this->map(function (RefundItem $refundItem) {

                return OrderItem::make([
                    'process_at' => now(),
                    'orderable_type' => $refundItem->getMorphClass(),
                    'orderable_id' => $refundItem->getKey(),
                    'owner_type' => $refundItem->owner_type,
                    'owner_id' => $refundItem->owner_id,
                    'description' => $refundItem->description,
                    'description_extra_lines' => $refundItem->description_extra_lines,
                    'currency' => $refundItem->currency,
                    'quantity' => $refundItem->quantity,
                    'unit_price' => - ($refundItem->unit_price),
                    'tax_percentage' => $refundItem->tax_percentage,
                    'order_id' => null,
                ]);
            }));
    }
}
