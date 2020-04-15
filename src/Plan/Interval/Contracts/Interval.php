<?php

namespace Laravel\Cashier\Plan\Interval\Contracts;

use Carbon\Carbon;
use Laravel\Cashier\Subscription;

interface Interval
{
    public function getNextSubscriptionCycle(Subscription $subscription = null): Carbon;
}
