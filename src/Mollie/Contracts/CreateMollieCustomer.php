<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie\Contracts;

use Mollie\Api\Resources\Customer;

interface CreateMollieCustomer
{
    public function execute(array $payload): Customer;
}
