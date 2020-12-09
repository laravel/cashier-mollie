<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie;

use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment as Contract;
use Mollie\Api\Resources\Payment;

class CreateMolliePayment extends BaseMollieInteraction implements Contract
{
    public function execute(array $payload): Payment
    {
        return $this->mollie->payments()->create($payload);
    }
}
