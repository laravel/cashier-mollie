<?php

namespace Laravel\Cashier\Plan\Interval;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Laravel\Cashier\Subscription;

class IntervalWithoutOverflow extends BaseInterval
{
    /** @var array $configuration */
    protected $configuration;

    public function __construct(array $configuration)
    {
        $this->configuration = $this->validateConfiguration($configuration);
    }

    public function getNextSubscriptionCycle(Subscription $subscription): Carbon
    {
        $lastBillingCycle = $subscription->cycle_ends_at->copy();

        $nextBillingCycleDate = $this->addPeriodWithoutOverflow(
            $lastBillingCycle,
            Arr::get($this->configuration, 'period'),
            Arr::get($this->configuration, 'value')
        );

        return $nextBillingCycleDate;
    }
}
