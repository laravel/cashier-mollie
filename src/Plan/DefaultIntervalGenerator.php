<?php


namespace Laravel\Cashier\Plan;

use Laravel\Cashier\Plan\Contracts\IntervalGeneratorContract;
use Laravel\Cashier\Subscription;

class DefaultIntervalGenerator implements IntervalGeneratorContract
{
    /**
     *
     * @var string
     */
    protected $interval;

    public function __construct(string $interval)
    {
        $this->interval = $interval;
    }

    /**
     * @param \Laravel\Cashier\Subscription|null $subscription
     *
     * @return \Carbon\Carbon|\Carbon\Traits\Modifiers
     */
    public function getEndOfTheNextSubscriptionCycle(Subscription  $subscription = null)
    {
        $cycle_ends_at = $subscription->cycle_ends_at ?? now();

        return $cycle_ends_at->copy()->modify('+' . $this->interval);
    }
}
