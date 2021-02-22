<?php
declare(strict_types=1);

namespace Laravel\Cashier\Refunds;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Order\ConvertsToMoney;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Traits\HasOwner;
use Money\Money;

/**
 * @property int quantity
 * @property string currency
 * @property int unit_price
 * @property float tax_percentage
 * @property int subtotal
 * @property int tax
 * @property int total
 * @property string owner_type
 * @property mixed owner_id
 * @property string description
 * @property array<string> description_extra_lines
 * @property OrderItem originalOrderItem
 * @method static create(array $array)
 * @method static make(array $array)
 */
class RefundItem extends Model
{
    use ConvertsToMoney;
    use HasOwner;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'description_extra_lines' => 'array',
    ];

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
            'owner_type' => $orderItem->owner_type,
            'owner_id' => $orderItem->owner_id,
            'description' => $orderItem->description,
            'currency' => $orderItem->getCurrency(),
            'quantity' => (int) $orderItem->quantity,
            'unit_price' => (int) $orderItem->unit_price,
            'tax_percentage' => (float) $orderItem->tax_percentage,
        ], $overrides));
    }

    public function originalOrderItem()
    {
        return $this->hasOne(OrderItem::class, 'id', 'original_order_item_id');
    }

    /**
     * Create a new RefundItemCollection instance.
     *
     * @param array $models
     * @return \Laravel\Cashier\Refunds\RefundItemCollection
     */
    public function newCollection(array $models = []): RefundItemCollection
    {
        return new RefundItemCollection($models);
    }

    /**
     * Get the unit price before taxes and discounts.
     *
     * @return \Money\Money
     */
    public function getUnitPrice(): Money
    {
        return $this->toMoney($this->unit_price);
    }

    /**
     * Get the order item total after taxes and discounts.
     *
     * @return \Money\Money
     */
    public function getTotal(): Money
    {
        return $this->toMoney($this->total);
    }

    /**
     * Get the order item total before taxes and discounts.
     *
     * @return \Money\Money
     */
    public function getSubtotal(): Money
    {
        return $this->toMoney($this->subtotal);
    }

    /**
     * The order item tax as a percentage.
     *
     * @return float
     * @example 21.5
     */
    public function getTaxPercentage()
    {
        return (float) $this->tax_percentage;
    }

    /**
     * The order item tax as a money value.
     *
     * @return \Money\Money
     */
    public function getTax(): Money
    {
        return $this->toMoney($this->tax);
    }

    /**
     * Get the order item total before taxes.
     *
     * @return int
     */
    public function getSubtotalAttribute(): int
    {
        return (int) $this->getUnitPrice()->multiply($this->quantity ?: 1)->getAmount();
    }

    /**
     * Get the order item tax money value.
     *
     * @return int
     */
    public function getTaxAttribute(): int
    {
        $beforeTax = $this->getSubtotal();

        return (int) $beforeTax->multiply($this->tax_percentage / 100)->getAmount();
    }

    /**
     * Get the order item total after taxes.
     *
     * @return int
     */
    public function getTotalAttribute(): int
    {
        $beforeTax = $this->getSubtotal();

        return (int) $beforeTax->add($this->getTax())->getAmount();
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }
}
