<?php

declare(strict_types=1);

namespace Laravel\Cashier\Plan\Contracts;

interface PlanRepository
{
    /**
     * @param string $name
     * @return null|\Laravel\Cashier\Plan\Contracts\Plan
     */
    public static function find(string $name);

    /**
     * @param string $name
     * @return \Laravel\Cashier\Plan\Contracts\Plan
     */
    public static function findOrFail(string $name);
}
