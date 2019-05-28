<?php

namespace Laravel\Cashier\Events;

use Laravel\Cashier\Credit\Credit;

class BalanceTurnedStale
{
    /**
     * @var \Laravel\Cashier\Credit\Credit
     */
    public $credit;

    public function __construct(Credit $credit)
    {
        $this->credit = $credit;
    }
}
