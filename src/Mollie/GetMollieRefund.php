<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie;

use Laravel\Cashier\Mollie\Contracts\GetMollieRefund as Contract;
use Mollie\Api\Resources\Refund;

class GetMollieRefund extends BaseMollieInteraction implements Contract
{
    public function execute(string $paymentId, string $refundId): Refund
    {
        return $this->mollie->paymentRefunds()->getForId($paymentId, $refundId);
    }
}
