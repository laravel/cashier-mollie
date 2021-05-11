<?php

namespace Laravel\Cashier\Charge;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Charge\Contracts\ChargeBuilder as Contract;
use Laravel\Cashier\FirstPayment\FirstPaymentBuilder;
use Laravel\Cashier\Http\RedirectToCheckoutResponse;

class FirstPaymentChargeBuilder implements Contract
{
    protected Model $owner;

    protected ChargeItemCollection $items;

    protected array $molliePaymentOverrides = [];

    public function __construct(Model $owner)
    {
        $this->owner = $owner;
        $this->items = new ChargeItemCollection;
    }

    public function setItems(ChargeItemCollection $items): self
    {
        $this->items = $items;

        return $this;
    }

    public function addItem(ChargeItem $item): self
    {
        $this->items->add($item);

        return $this;
    }

    public function molliePaymentOverrides(array $overrides): self
    {
        $this->molliePaymentOverrides = $overrides;

        return $this;
    }

    public function create()
    {
        if ($this->items->isEmpty()) {
            throw new \LogicException('Charge item list cannot be empty');
        }

        $firstPaymentBuilder = new FirstPaymentBuilder($this->owner, $this->molliePaymentOverrides);

        $molliePayment = $firstPaymentBuilder
            ->inOrderTo($this->items->toFirstPaymentActionCollection()->all())
            ->create();

        return RedirectToCheckoutResponse::forPayment($molliePayment);
    }
}
