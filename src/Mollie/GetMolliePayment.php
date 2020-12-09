<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie;

use Laravel\Cashier\Mollie\Contracts\GetMolliePayment as Contract;
use Mollie\Api\Resources\Payment;

class GetMolliePayment extends BaseMollieInteraction implements Contract
{
    public function execute(string $id): Payment
    {
        return $this->mollie->payments()->get($id);
    }
}
