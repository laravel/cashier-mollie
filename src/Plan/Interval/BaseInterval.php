<?php

namespace Laravel\Cashier\Plan\Interval;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Laravel\Cashier\Exceptions\IntervalConfigrationInvalidException;
use Laravel\Cashier\Plan\Interval\Contracts\Interval;

abstract class BaseInterval implements Interval
{
    protected $allowedPeriodEntries = [
        'day',
        'days',
        'month',
        'months',
        'year',
        'years',
    ];

    protected function addPeriodWithoutOverflow(Carbon $date,string $period, int $value): Carbon
    {
        throw_unless(
            Arr::exists($this->allowedPeriodEntries, $period),
            IntervalConfigrationInvalidException::class
        );

        $carbonAddPeriodMethodName = 'add' . ucfirst($period) . 'WithoutOverflow';

        return $date->$carbonAddPeriodMethodName($value);
    }

    protected function validateConfiguration(array $configuration): array
    {
        throw_unless(
            (is_string(Arr::get($this->configuration, 'period')) &&
                is_int(Arr::get($this->configuration, 'value'))),
            IntervalConfigrationInvalidException::class
        );

        return $configuration;
    }
}
