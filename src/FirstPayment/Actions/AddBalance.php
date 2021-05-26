<?php

namespace Laravel\Cashier\FirstPayment\Actions;

use Illuminate\Database\Eloquent\Model;
use Money\Money;

class AddBalance extends AddGenericOrderItem
{
    /**
     * AddBalance constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param \Money\Money $subtotal
     * @param int $quantity
     * @param string $description
     */
    public function __construct(Model $owner, Money $subtotal, int $quantity, string $description)
    {
        parent::__construct($owner, $subtotal, $quantity, $description);

        $this->taxPercentage = 0; // Adding balance is NOT taxed by default
    }

    /**
     * Execute this action and return the created OrderItemCollection.
     *
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function execute()
    {
        $this->owner->addCredit($this->getSubtotal());

        return parent::execute();
    }
}
