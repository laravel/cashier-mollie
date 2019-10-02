<?php

namespace Laravel\Cashier\FirstPayment\Actions;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Order\OrderItemCollection;

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
     * Prepare a stub of OrderItems processed with the payment.
     *
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    abstract public function makeProcessedOrderItems();

    /**
     * Execute this action and return the created OrderItemCollection.
     *
     * @return \Laravel\Cashier\Order\OrderItemCollection
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
        return $this->currency ?? strtoupper(Cashier::usesCurrency());
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
