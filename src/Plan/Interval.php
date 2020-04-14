<?php

namespace Laravel\Cashier\Plan;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Laravel\Cashier\Exceptions\IntervalConfigrationInvalidException;

class Interval
{
    protected $configuration;

    private $allowedPeriodEntries = [
        'day',
        'days',
        'month',
        'months',
        'year',
        'years',
    ];

    /**
     * Interval constructor.
     * @param array|string $configuration
     */
    public function __construct($configuration)
    {
        $this->validateConfiguration($configuration);
        $this->configuration = $configuration;
    }

    /**
     * @param Carbon $lastBillingCycle
     * @return Carbon The next billing date
     */
    public function getNextSubscriptionCycle(Carbon $lastBillingCycle, Carbon $subscriptionCreationDate): Carbon
    {
        if (is_string($this->configuration)){
            return $lastBillingCycle->modify('+' . $this->configuration);
        }

        $subscriptionDayOfMonth = $subscriptionCreationDate->day;
        $carbonAddPeriodMethodName = 'add' . ucfirst($this->period()) . 'WithoutOverflow';
        $nextBillingCycleDate = $lastBillingCycle->$carbonAddPeriodMethodName($this->value());

        // Always set the next billing day to the original day of month of the initial subscription
        if ($this->isFixed()) {
            if ($subscriptionCreationDate->isLastOfMonth()) {
                $nextBillingCycleDate = $nextBillingCycleDate->endOfMonth();
            } elseif ($subscriptionDayOfMonth > $nextBillingCycleDate->day) {
                $nextBillingCycleDate = $this->calculateAlternativeBillingDate($nextBillingCycleDate, $subscriptionDayOfMonth);
            }
        }

        return $nextBillingCycleDate;
    }

    /**
     * @param $configuration
     * @throws IntervalConfigrationInvalidException
     */
    protected function validateConfiguration($configuration)
    {
        throw_unless(
            (is_string($configuration) || is_array($configuration)),
            IntervalConfigrationInvalidException::class
        );

        throw_unless(
            Arr::exists($this->allowedPeriodEntries, $this->period()),
            IntervalConfigrationInvalidException::class
        );
    }

    protected function value(): int
    {
        return Arr::get($this->configuration, 'value');
    }

    protected function period(): string
    {
        return Arr::get($this->configuration, 'period');
    }

    protected function isFixed(): bool
    {
        $fixed = Arr::get($this->configuration, 'fixed');
        return is_bool($fixed) ? $fixed : false;
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
    private function calculateAlternativeBillingDate(Carbon $nextBillingCycleDate, int $subscriptionDayOfMonth)
    {
        $resetBillingCycleDate = $nextBillingCycleDate->day($subscriptionDayOfMonth);
        if ($resetBillingCycleDate->isSameMonth($nextBillingCycleDate)) {
            $nextBillingCycleDate = $resetBillingCycleDate->subMonth()->endOfMonth();
        }

        return $nextBillingCycleDate;
    }
}
