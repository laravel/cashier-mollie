<?php
declare(strict_types=1);

namespace Laravel\Cashier\Refunds;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Order\OrderItem;

class RefundItem extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var string[]|bool
     */
    protected $guarded = [];

    /**
     * @param \Laravel\Cashier\Order\OrderItem $orderItem
     * @param array $overrides
     * @return static
     */
    public static function makeFromOrderItem(OrderItem $orderItem, array $overrides = []): self
    {
        return static::make(array_merge([
            'original_order_item_id' => $orderItem->getKey(),
            'currency' => $orderItem->getCurrency(),
            'quantity' => $orderItem->quantity,
            'unit_price' => $orderItem->unit_price,
            'tax_percentage' => $orderItem->tax_percentage,
        ], $overrides));
    }

    public function newCollection(array $models = [])
    {
        return new RefundItemCollection($models);
    }
}
