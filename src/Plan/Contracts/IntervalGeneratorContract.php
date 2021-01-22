<?php


namespace Laravel\Cashier\Plan\Contracts;

use Laravel\Cashier\Subscription;

interface IntervalGeneratorContract
{
    /**
     * @param \Laravel\Cashier\Subscription $subscription
     *
     * @return \Carbon\Carbon
     */
    public function getEndOfTheNextSubscriptionCycle(Subscription  $subscription);
}
