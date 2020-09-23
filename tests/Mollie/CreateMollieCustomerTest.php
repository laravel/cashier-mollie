<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Mollie;

use Laravel\Cashier\Mollie\Contracts\CreateMollieCustomer;
use Laravel\Cashier\Tests\BaseTestCase;
use Mollie\Api\Resources\Customer;

class CreateMollieCustomerTest extends BaseTestCase
{
    /**
     * @test
     * @group integration
     */
    public function testExecute()
    {
        /** @var CreateMollieCustomer $action */
        $action = $this->app->make(CreateMollieCustomer::class);

        $result = $action->execute([
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertEquals('john@example.com', $result->email);
        $this->assertEquals('John Doe', $result->name);
    }
}
