<?php

namespace Laravel\Cashier\Charge;

trait ManagesCharges
{
    public function newFirstPaymentChargeThroughCheckout()
    {
        return new FirstPaymentChargeBuilder($this);
    }
}
