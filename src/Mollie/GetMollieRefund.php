<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie;

use Laravel\Cashier\Mollie\Contracts\GetMolliePayment;
use Laravel\Cashier\Mollie\Contracts\GetMollieRefund as Contract;
use Mollie\Api\Resources\Refund;
use Mollie\Laravel\Wrappers\MollieApiWrapper as Mollie;

class GetMollieRefund implements Contract
{
    /**
     * @var \Mollie\Laravel\Wrappers\MollieApiWrapper
     */
    protected Mollie $mollie;

    /**
     * @var \Laravel\Cashier\Mollie\Contracts\GetMolliePayment
     */
    protected GetMolliePayment $getMolliePayment;

    public function __construct(Mollie $mollie, GetMolliePayment $getMolliePayment)
    {
        $this->mollie = $mollie;
        $this->getMolliePayment = $getMolliePayment;
    }

    public function execute(string $paymentId, string $refundId): Refund
    {
        $payment = $this->getMolliePayment->execute($paymentId);

        return $payment->getRefund($refundId);
    }
}
