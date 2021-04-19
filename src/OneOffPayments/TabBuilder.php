<?php

namespace Laravel\Cashier\OneOffPayments;

use Illuminate\Database\Eloquent\Model;

class TabBuilder
{
    protected Model $owner;

    protected string $currency;

    protected float $defaultTaxPercentage;

    public function __construct(Model $owner, string $currency)
    {
        $this->owner = $owner;
        $this->currency = $currency;
    }

    public function withCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function withDefaultTaxPercentage(float $taxPercentage): self
    {
        $this->defaultTaxPercentage = $taxPercentage;

        return $this;
    }

    public function create(): Tab
    {
        return $this->owner->tabs()->create([
            'default_tax_percentage' => $this->defaultTaxPercentage,
            'currency' => $this->currency,
        ]);
    }
}
