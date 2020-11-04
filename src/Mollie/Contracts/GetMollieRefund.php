<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie\Contracts;

use Mollie\Api\Resources\Refund;

interface GetMollieRefund
{
    public function execute(string $paymentId, string $refundId): Refund;
}
