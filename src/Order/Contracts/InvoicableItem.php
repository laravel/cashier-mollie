<?php

namespace Laravel\Cashier\Order\Contracts;

interface InvoicableItem
{
    /**
     * @return \Money\Money
     */
    public function getUnitPrice();

    /**
     * @return \Money\Money
     */
    public function getTotal();

    /**
     * @return \Money\Money
     */
    public function getSubtotal();

    /**
     * @return float
     * @example 21.5
     */
    public function getTaxPercentage();

    /**
     * @return \Money\Money
     */
    public function getTax();
}
