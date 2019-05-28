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
     */
    public function __construct(Model $owner, Money $subtotal, string $description)
    {
        $this->owner = $owner;
        $this->taxPercentage = $this->owner->taxPercentage();
        $this->subtotal = $subtotal;
        $this->currency = $subtotal->getCurrency()->getCode();
        $this->description = $description;
    }

    /**
     * @param array $payload
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @return self
     */
    public static function createFromPayload(array $payload, Model $owner)
    {
        return (new static(
            $owner,
            mollie_array_to_money($payload['subtotal']),
            $payload['description']
        ))->withTaxPercentage($payload['taxPercentage']);
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
     * Execute this action and return the created OrderItem or OrderItemCollection.
     *
     * @return \Laravel\Cashier\Order\OrderItem|\Laravel\Cashier\Order\OrderItemCollection
     */
    public function execute()
    {
        return $this->owner->orderItems()->create([
            'description' => $this->getDescription(),
            'currency' => $this->getCurrency(),
            'process_at' => now(),
            'unit_price' => $this->getSubtotal()->getAmount(),
            'tax_percentage' => $this->getTaxPercentage(),
            'quantity' => 1,
        ]);
    }
}
