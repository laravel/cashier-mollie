<?php

namespace Laravel\Cashier\Charge\Contracts;

use Laravel\Cashier\Charge\ChargeItem;
use Laravel\Cashier\Charge\ChargeItemCollection;

interface ChargeBuilder
{
    public function addItem(ChargeItem $item): self;

    public function setItems(ChargeItemCollection $items): self;

    public function create();
}
