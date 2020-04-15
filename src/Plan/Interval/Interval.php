<?php

namespace Laravel\Cashier\Plan\Interval;

use Carbon\Carbon;
use Laravel\Cashier\Plan\Interval\Contracts\Interval as IntervalContract;
use Laravel\Cashier\Subscription;

class Interval implements IntervalContract
{
    /** @var string $configuration */
    protected $configuration;

    public function __construct(string $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getNextSubscriptionCycle(Subscription $subscription): Carbon
    {
        return $subscription->cycle_ends_at->copy()->modify('+' . $this->configuration);
    }
}
