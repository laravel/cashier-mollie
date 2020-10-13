<?php
declare(strict_types=1);

namespace Laravel\Cashier\Mollie;

use Mollie\Api\Resources\Customer;
use Laravel\Cashier\Mollie\Contracts\GetMollieCustomer as Contract;

class GetMollieCustomer extends BaseMollieInteraction implements Contract
{
    public function execute(string $id): Customer
    {
        return $this->mollie->customers()->get($id);
    }
}
