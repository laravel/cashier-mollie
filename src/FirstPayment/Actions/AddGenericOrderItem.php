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
     * @param \Money\Money $subtotal
     * @param string $description
     * @param int $roundingMode
     */
    public function __construct(Model $owner, Money $subtotal, string $description, int $roundingMode = Money::ROUND_HALF_UP)
    {
        $this->owner = $owner;
        $this->taxPercentage = $this->owner->taxPercentage();
        $this->unitPrice = $subtotal;
        $this->currency = $subtotal->getCurrency()->getCode();
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
        $taxPercentage = isset($payload['taxPercentage']) ? $payload['taxPercentage'] : 0;

        return (new static(
            $owner,
            mollie_array_to_money($payload['subtotal']),
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
            'subtotal' => money_to_mollie_array($this->getSubtotal()),
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
            'unit_price' => $this->getSubtotal()->getAmount(),
            'tax_percentage' => $this->getTaxPercentage(),
            'quantity' => 1,
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
