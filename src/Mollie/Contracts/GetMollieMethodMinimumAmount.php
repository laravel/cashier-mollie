<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie\Contracts;

use Money\Money;

interface GetMollieMethodMinimumAmount
{
    public function execute(string $method, string $currency): Money;
}
