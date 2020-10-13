<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie;

use Mollie\Api\Resources\Mandate;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate as Contract;

class GetMollieMandate extends BaseMollieInteraction implements Contract
{
    public function execute(string $customerId, string $mandateId): Mandate
    {
        return $this->mollie->mandates()->getForId($customerId, $mandateId);
    }
}
