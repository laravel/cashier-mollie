<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie;

use Mollie\Api\Resources\Payment;
use Laravel\Cashier\Mollie\Contracts\UpdateMolliePayment as Contract;

class UpdateMolliePayment extends BaseMollieInteraction implements Contract
{
    public function execute(Payment $dirtyPayment): Payment
    {
        return $dirtyPayment->update();
    }
}
