<?php

namespace Laravel\Cashier\FirstPayment\Actions;

use Illuminate\Database\Eloquent\Model;
use Money\Money;

class AddGenericOrderItem extends BaseAction
{
    /**
     * AddGenericOrderItem constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param \Money\Money $unitPrice
     * @param int $quantity
     * @param string $description
     * @param int $roundingMode
     */
    public function __construct(Model $owner, Money $unitPrice, int $quantity, string $description, int $roundingMode = Money::ROUND_HALF_UP)
    {
        $this->owner = $owner;
        $this->taxPercentage = $this->owner->taxPercentage();
        $this->unitPrice = $unitPrice;
        $this->quantity = $quantity;
        $this->currency = $unitPrice->getCurrency()->getCode();
        $this->description = $description;
        $this->roundingMode = $roundingMode;
    }

    /**
     * @param array $payload
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @return self
     */
    public static function createFromPayload(array $payload, Model $owner)
    {
        $taxPercentage = $payload['taxPercentage'] ?? 0;
        $quantity = $payload['quantity'] ?? 1;
        $unit_price = $payload['subtotal'] ?? $payload['unit_price'];

        return (new static(
            $owner,
            mollie_array_to_money($unit_price),
            $quantity,
            $payload['description']
        ))->withTaxPercentage($taxPercentage);
    }

    /**
     * @return array
     */
    public function getPayload()
    {
        return [
            'handler' => static::class,
            'description' => $this->getDescription(),
            'unit_price' => money_to_mollie_array($this->getUnitPrice()),
            'quantity' => $this->getQuantity(),
            'taxPercentage' => $this->getTaxPercentage(),
        ];
    }

    /**
     * Prepare a stub of OrderItems processed with the payment.
     *
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function makeProcessedOrderItems()
    {
        return $this->owner->orderItems()->make([
            'description' => $this->getDescription(),
            'currency' => $this->getCurrency(),
            'process_at' => now(),
            'unit_price' => $this->getUnitPrice()->getAmount(),
            'tax_percentage' => $this->getTaxPercentage(),
            'quantity' => $this->getQuantity(),
        ])->toCollection();
    }

    /**
     * Execute this action and return the created OrderItemCollection.
     *
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function execute()
    {
        return tap($this->makeProcessedOrderItems(), function ($items) {
            $items->save();
        });
    }
}
