<?php


namespace Laravel\Cashier\Plan;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Cashier\Plan\Contracts\IntervalGeneratorContract;
use Laravel\Cashier\Subscription;

class AdvancedIntervalGenerator extends BaseIntervalGenerator implements IntervalGeneratorContract
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
    public function getEndOfNextSubscriptionCycle(Subscription  $subscription = null)
    {
        $cycle_ends_at = $subscription->cycle_ends_at ?? now();
        $subscription_date = $this->startOfTheSubscription($subscription);

        if ($this->isMonthly() && $this->isFixed()) {
            return $cycle_ends_at->addMonthsNoOverflow($this->value())->thisDayOrLastOfTheMonth($subscription_date);
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
    protected function isMonthly()
    {
        return Str::startsWith($this->period(), 'month');
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
}
