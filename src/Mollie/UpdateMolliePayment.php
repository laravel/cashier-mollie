<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie;

use Laravel\Cashier\Mollie\Contracts\UpdateMolliePayment as Contract;
use Mollie\Api\Resources\Payment;

class UpdateMolliePayment extends BaseMollieInteraction implements Contract
{
    public function execute(Payment $dirtyPayment): Payment
    {
        return $dirtyPayment->update();
    }
}
