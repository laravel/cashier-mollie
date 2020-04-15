<?php

namespace Laravel\Cashier\Plan\Interval;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Laravel\Cashier\Subscription;

class FixedInterval extends BaseInterval
{
    /** @var array $configuration */
    protected $configuration;

    protected $allowedPeriodEntries = [
        'month',
        'months',
        'year',
        'years',
    ];

    public function __construct(array $configuration)
    {
        $this->configuration = $this->validateConfiguration($configuration);
    }


    public function getNextSubscriptionCycle(Subscription $subscription = null): Carbon
    {
        if ($subscription instanceof Subscription) {
            $lastBillingCycle = $subscription->cycle_ends_at->copy();
            $subscriptionCreatedAt = $subscription->created_at;
        } else {
            $lastBillingCycle = now();
            $subscriptionCreatedAt = now();
        }

        $subscriptionDayOfMonth = $lastBillingCycle->day;

        $nextBillingCycleDate = $this->addPeriodWithoutOverflow(
            $lastBillingCycle,
            Arr::get($this->configuration, 'period'),
            Arr::get($this->configuration, 'value')
        );

        if ($subscriptionCreatedAt->isLastOfMonth()) {
            $nextBillingCycleDate = $nextBillingCycleDate->endOfMonth();
        } elseif ($subscriptionDayOfMonth > $nextBillingCycleDate->day) {
            $nextBillingCycleDate = $this->calculateAlternativeBillingDay($nextBillingCycleDate, $subscriptionDayOfMonth);
        }

        return $nextBillingCycleDate;
    }

    /**
     * Fix subscription dates for intervals which would be overridden in february
     *
     * e.g. 2020-01-30 + 1 month = 2020-02-29 + 1 month = 2020-03-29 -> correct date would be 2020-03-30
     *
     * @param Carbon $nextBillingCycleDate
     * @param int $subscriptionDayOfMonth
     * @return Carbon
     */
    private function calculateAlternativeBillingDay(Carbon $nextBillingCycleDate, int $subscriptionDayOfMonth)
    {
        $alternativeDate = $nextBillingCycleDate->day($subscriptionDayOfMonth);

        if ($alternativeDate->isSameMonth($nextBillingCycleDate)) {
            $nextBillingCycleDate = $alternativeDate->subMonth()->endOfMonth();
        }

        return $nextBillingCycleDate;
    }
}
