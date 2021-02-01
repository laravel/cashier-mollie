<?php


namespace Laravel\Cashier\Plan;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Cashier\Plan\Contracts\IntervalGeneratorContract;
use Laravel\Cashier\Subscription;

class AdvancedIntervalGenerator implements IntervalGeneratorContract
{
    /**
     *
     * @var array
     */
    protected $configuration;

    public function __construct(array $configuration)
    {
        unset($configuration['generator']);

        $this->useCarbonThisDayOrLast();
        $this->configuration = $configuration;
    }

    /**
     * @param \Laravel\Cashier\Subscription|null $subscription
     *
     * @return \Carbon\Carbon|\Carbon\Traits\Modifiers
     */
    public function getEndOfTheNextSubscriptionCycle(Subscription  $subscription = null)
    {
        $cycle_ends_at = $subscription->cycle_ends_at ?? now();
        $subscription_day = $this->dayOfStartSubscription($subscription);

        if ($this->isMonthly() && $this->isFixed()) {
            return $cycle_ends_at->addMonthsNoOverflow()->thisDayOrLastOfTheMonth($subscription_day);
        }

        return $cycle_ends_at->modify('+' . $this->value() . ' '. $this->period());
    }

    /**
     *
     * @return int
     */
    protected function value()
    {
        return Arr::get($this->configuration, 'value');
    }

    /**
     *
     * @return string
     */
    protected function period()
    {
        return Arr::get($this->configuration, 'period');
    }

    /**
     *
     * @return bool
     */
    protected function isFixed()
    {
        $fixed = Arr::get($this->configuration, 'fixed');

        return is_bool($fixed) ? $fixed : false;
    }

    /**
     *
     * @return bool
     */
    protected function isMonthly()
    {
        return Str::startsWith($this->period(), 'month');
    }

    protected function dayOfStartSubscription(Subscription  $subscription = null)
    {
        if (isset($subscription->trial_ends_at) && ! is_null($subscription->trial_ends_at)) {
            return $subscription->trial_ends_at->day;
        }

        return $subscription_started_at = $subscription->created_at->day ?? now()->day;
    }

    protected function useCarbonThisDayOrLast()
    {
        Carbon::macro('thisDayOrLastOfTheMonth', function ($day) {
            $last = $this->lastOfMonth();

            $this->day = ($day > $last->day) ? $last->day : $day;

            return $this;
        });
    }
}
