<?php


namespace Laravel\Cashier\Plan;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;

class IntervalGenerator
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
    public function getNextSubscriptionCycle(Subscription  $subscription = null)
    {
        $cycle_ends_at = $subscription->cycle_ends_at ?? now();
        $subscription_started_at = $subscription->created_at ?? now();

        if (! $cycle_ends_at) {
            return Carbon::parse($this->value() . " " . $this->period());
        }
        if ($this->isMonthly() && $this->isFixed()) {
            return $cycle_ends_at->copy()->addMonthsNoOverflow()->thisDayOrLast($subscription_started_at->day);
        }

        return $cycle_ends_at->copy()->modify('+' . $this->value() . ' '. $this->period());
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

    public function useCarbonThisDayOrLast()
    {
        Carbon::macro('thisDayOrLast', function ($day) {
            $last = $this->copy()->lastOfMonth();

            $this->day = ($day > $last->day) ? $last->day : $day;

            return $this;
        });
    }
}
