<?php
declare(strict_types=1);

namespace Laravel\Cashier\OneOffPayments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Cashier\Order\Contracts\InteractsWithOrderItems;
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
 * @property int|null order_item_id
 * @property string description
 * @property array<string> description_extra_lines
 * @method static create(array $array)
 * @method static make(array $array)
 */
class TabItem extends Model implements InteractsWithOrderItems
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
     * Create a new Eloquent Collection instance.
     *
     * @param array $models
     * @return \Laravel\Cashier\OneOffPayments\TabItemCollection
     */
    public function newCollection(array $models = [])
    {
        return new TabItemCollection($models);
    }

    /**
     * Return the tab for this tab item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tab(): BelongsTo
    {
        return $this->belongsTo(Tab::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function orderItem(): HasOne
    {
        return $this->hasOne(OrderItem::class, 'id', 'order_item_id');
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
    public function getTaxPercentage(): float
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

    public static function preprocessOrderItem(OrderItem $item)
    {
        // Nothing to do here

        return $item->toCollection();
    }

    public static function processOrderItem(OrderItem $item)
    {
        // TODO if exists, call processOrderItem on tabbed item
    }

    public static function handlePaymentFailed(OrderItem $item)
    {
        // TODO mark as failed, fire event
        // TODO if exists, call handlePaymentFailed on tabbed item
    }

    public static function handlePaymentPaid(OrderItem $item)
    {
        // TODO: mark as paid, fire event
        // TODO if exists, call handlePaymentPaid on tabbed item
    }
}
