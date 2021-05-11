<?php

namespace Laravel\Cashier\Charge;

use Laravel\Cashier\FirstPayment\Actions\ActionCollection as FirstPaymentActionCollection;

class ChargeItemCollection extends \Illuminate\Support\Collection
{
    public function toFirstPaymentActionCollection(): FirstPaymentActionCollection
    {
        $result = $this->map(function (ChargeItem $item) {
            return $item->toFirstPaymentAction();
        });

        return new FirstPaymentActionCollection($result->all());
    }
}
