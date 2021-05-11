<?php

namespace Laravel\Cashier\Charge;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\FirstPayment\FirstPaymentBuilder;

class FirstPaymentChargeBuilder
{
    protected Model $owner;
    protected ChargeItemCollection $items;
    protected FirstPaymentBuilder $firstPaymentBuilder;

    public function __construct(Model $owner)
    {
        $this->owner = $owner;
        $this->firstPaymentBuilder = new FirstPaymentBuilder($owner);
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

    public function create()
    {
        if ($this->items->isEmpty()) {
            throw new \LogicException('Charge item list cannot be empty');
        }

        // TODO override webhook / redirectUrl etc?

        $molliePayment = $this->firstPaymentBuilder
            ->inOrderTo($this->items->toFirstPaymentActionCollection()->all())
            ->create();

        // TODO return something usable
    }
}
