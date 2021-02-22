<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie\Contracts;

use Mollie\Api\Resources\Refund;

interface CreateMollieRefund
{
    public function execute(string $paymentId, array $payload): Refund;
}
