<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Mollie;

use Laravel\Cashier\Mollie\Contracts\GetMollieCustomer;
use Mollie\Api\Resources\Customer;

class GetMollieCustomerTest extends BaseMollieInteractionTest
{
    /**
     * @test
     * @group mollie_integration
     */
    public function testExecute()
    {
        /** @var GetMollieCustomer $action */
        $action = $this->app->make(GetMollieCustomer::class);
        $id = $this->getMandatedCustomerId();
        $result = $action->execute($id);

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertEquals($id, $result->id);
    }
}
