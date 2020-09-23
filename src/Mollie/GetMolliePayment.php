<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie;

use Mollie\Api\Resources\Payment;
use Laravel\Cashier\Mollie\Contracts\GetMolliePayment as Contract;


class GetMolliePayment extends BaseMollieInteraction implements Contract
{
    public function execute(string $id): Payment
    {
        return $this->mollie->payments()->get($id);
    }
}
