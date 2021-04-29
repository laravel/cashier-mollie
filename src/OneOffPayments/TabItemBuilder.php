<?php

namespace Laravel\Cashier\OneOffPayments;

class TabItemBuilder
{
    /**
     * @var \Laravel\Cashier\OneOffPayments\Tab
     */
    protected Tab $tab;

    protected string $description;

    protected int $unitPrice;

    protected int $quantity = 1;

    protected float $vatPercentage = 0;

    protected array $descriptionExtraLines = [];

    public function __construct(
        Tab $tab,
        string $description,
        int $unitPrice
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
        $this->vatPercentage = $percentage ?? $this->tab->default_tax_percentage;

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
            'description' => $this->description,
            'description_extra_lines' => $this->descriptionExtraLines,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'tax_percentage' => $this->vatPercentage,
        ]);
    }
}
