<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Order\Contracts\MinimumPayment as MinimumPaymentContract;
use Mollie\Api\Resources\Mandate;

class MinimumPayment implements MinimumPaymentContract
{
    /**
     * @param \Mollie\Api\Resources\Mandate $mandate
     * @param $currency
     * @return \Money\Money
     */
    public static function forMollieMandate(Mandate $mandate, $currency)
    {
        return mollie_object_to_money(
            mollie()
                ->methods()->get($mandate->method, ['currency' => $currency])
                ->minimumAmount
        );
    }

    /**
     * @param string $mandateId
     * @param string $currency
     * @return \Money\Money
     */
    public static function forMollieMandateId(string $mandateId, string $currency)
    {
        return static::forMollieMandate(
            mollie()->mandates()->get($mandateId),
            $currency
        );
    }
}
