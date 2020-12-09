<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie;

use Laravel\Cashier\Mollie\Contracts\GetMollieMandate as Contract;
use Mollie\Api\Resources\Mandate;

class GetMollieMandate extends BaseMollieInteraction implements Contract
{
    public function execute(string $customerId, string $mandateId): Mandate
    {
        return $this->mollie->mandates()->getForId($customerId, $mandateId);
    }
}
