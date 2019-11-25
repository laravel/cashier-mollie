<?php

namespace Laravel\Cashier\Events;

use Illuminate\Queue\SerializesModels;
use Laravel\Cashier\Credit\Credit;

class BalanceTurnedStale
{
    use SerializesModels;

    /**
     * @var \Laravel\Cashier\Credit\Credit
     */
    public $credit;

    public function __construct(Credit $credit)
    {
        $this->credit = $credit;
    }
}
