<?php


namespace Laravel\Cashier\Plan;

use Illuminate\Support\Str;
use Laravel\Cashier\Plan\Contracts\IntervalGeneratorContract;
use Laravel\Cashier\Subscription;

class DefaultIntervalGenerator extends BaseIntervalGenerator implements IntervalGeneratorContract
{
    /**
     *
     * @var string
     */
    protected $interval;

    public function __construct(string $interval)
    {
        $this->interval = $interval;
        $this->useCarbonThisDayOrLast();
    }

    /**
     * @param \Laravel\Cashier\Subscription|null $subscription
     *
     * @return \Carbon\Carbon|\Carbon\Traits\Modifiers
     */
    public function getEndOfTheNextSubscriptionCycle(Subscription  $subscription = null)
    {
        $cycle_ends_at = $subscription->cycle_ends_at ?? now();
        $subscription_date = $this->startOfTheSubscription($subscription);

        if ($this->isMonthly()) {
            return $cycle_ends_at->addMonthsNoOverflow((int) filter_var($this->interval, FILTER_SANITIZE_NUMBER_INT))
                ->thisDayOrLastOfTheMonth($subscription_date);
        }

        return $cycle_ends_at->modify('+' . $this->interval);
    }

    /**
     *
     * @return bool
     */
    protected function isMonthly()
    {
        return Str::contains($this->interval, 'month');
    }
}
