<?php

namespace Laravel\Cashier\FirstPayment\Actions;

use Illuminate\Database\Eloquent\Model;

abstract class BaseAction
{
    /** var string */
    protected $description;

    /** @var string */
    protected $currency;

    /** @var \Money\Money */
    protected $subtotal;

    /** var float */
    protected $taxPercentage = 0;

    /** @var \Money\Money */
    protected $discount;

    /** @var string */
    protected $discountDescription;

    /** @var \Illuminate\Database\Eloquent\Model */
    protected $owner;

    /**
     * Rebuild the Action from a payload.
     *
     * @param array $payload
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @return BaseAction
     */
    abstract public static function createFromPayload(array $payload, Model $owner);

    /**
     * @return array
     */
    abstract public function getPayload();

    /**
     * Execute this action and return the created OrderItem or OrderItemCollection.
     *
     * @return \Laravel\Cashier\Order\OrderItem|\Laravel\Cashier\Order\OrderItemCollection
     */
    abstract public function execute();

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return float
     */
    public function getTaxPercentage()
    {
        return $this->taxPercentage;
    }

    /**
     * @param float $percentage
     * @example 21.5
     * @return $this
     */
    public function withTaxPercentage(float $percentage)
    {
        $this->taxPercentage = $percentage;

        return $this;
    }

    /**
     * @return \Money\Money
     */
    public function getSubtotal()
    {
        return $this->subtotal;
    }

    /**
     * @return \Money\Money
     */
    public function getTax()
    {
        return $this->getSubtotal()
                    ->multiply($this->getTaxPercentage())
                    ->divide(100);
    }

    /**
     * The total after tax and discounts.
     *
     * @return \Money\Money
     */
    public function getTotal()
    {
        return $this->getSubtotal()->add($this->getTax());
    }
}
