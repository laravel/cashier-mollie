<?php

namespace Laravel\Cashier\OneOffPayments;

use Money\Money;

class TabItemBuilder
{
    /**
     * @var \Laravel\Cashier\OneOffPayments\Tab
     */
    protected Tab $tab;

    protected string $description;

    protected Money $unitPrice;

    protected int $quantity = 1;

    protected float $vatPercentage = 0;

    protected array $descriptionExtraLines = [];

    public function __construct(
        Tab $tab,
        string $description,
        Money $unitPrice
    ) {
        $this->tab = $tab;
        $this->description = $description;
        $this->unitPrice = $unitPrice;
    }

    /**
     * @param int $quantity
     * @return $this
     * @throws \Throwable|\LogicException
     */
    public function quantity(int $quantity): self
    {
        throw_if($quantity < 1, new \LogicException('Quantity must be at least 1.'));

        $this->quantity = $quantity;

        return $this;
    }

    public function vatPercentage(float $percentage): self
    {
        $this->vatPercentage = $percentage;

        return $this;
    }

    public function descriptionExtraLines(array $lines): self
    {
        $this->descriptionExtraLines = $lines;

        return $this;
    }

    public function create()
    {
        return $this->tab->items()->create([
            // TODO store item
        ]);
    }
}
