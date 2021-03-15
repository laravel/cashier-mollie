<?php
declare(strict_types=1);

namespace Laravel\Cashier\OneOffPayment;

use Illuminate\Support\Collection;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Money\Money;

class TabItemCollection extends Collection
{
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
            $this->map(function (TabItem $tabItem) {
                return OrderItem::make([
                    'process_at' => now(),
                    'orderable_type' => $tabItem->getMorphClass(),
                    'orderable_id' => $tabItem->getKey(),
                    'owner_type' => $tabItem->owner_type,
                    'owner_id' => $tabItem->owner_id,
                    'description' => $tabItem->description,
                    'description_extra_lines' => $tabItem->description_extra_lines,
                    'currency' => $tabItem->currency,
                    'quantity' => $tabItem->quantity,
                    'unit_price' => $tabItem->unit_price,
                    'tax_percentage' => $tabItem->tax_percentage,
                    'order_id' => null,
                ]);
            })
        );
    }
}
