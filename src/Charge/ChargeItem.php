<?php

namespace Laravel\Cashier\Charge;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\FirstPayment\Actions\AddGenericOrderItem;
use Laravel\Cashier\FirstPayment\Actions\BaseAction as FirstPaymentAction;
use Money\Money;

class ChargeItem
{
    protected Model $owner;
    protected Money $unitPrice;
    protected string $description;
    protected int $quantity;
    protected float $taxPercentage;
    protected int $roundingMode;

    public function __construct(
        Model $owner,
        Money $unitPrice,
        string $description,
        int $quantity = 1,
        float $taxPercentage = 0,
        int $roundingMode = Money::ROUND_HALF_UP
    ) {
        $this->owner = $owner;
        $this->unitPrice = $unitPrice;
        $this->description = $description;
        $this->quantity = $quantity;
        $this->taxPercentage = $taxPercentage;
        $this->roundingMode = $roundingMode;
    }

    public function toFirstPaymentAction(): FirstPaymentAction
    {
        $item = new AddGenericOrderItem(
            $this->owner,
            $this->unitPrice,
            $this->description,
            $this->roundingMode
        );

        $item->withQuantity($this->quantity)
             ->withTaxPercentage($this->taxPercentage);

        return $item;
    }
}
