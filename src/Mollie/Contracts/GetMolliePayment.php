<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie\Contracts;

use Mollie\Api\Resources\Payment;

interface GetMolliePayment
{
    public function execute(string $id): Payment;
}
