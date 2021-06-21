<?php

namespace Laravel\Cashier\Tests\Charge;

use Laravel\Cashier\Charge\FirstPaymentChargeBuilder;
use Laravel\Cashier\Charge\MandatedChargeBuilder;
use Laravel\Cashier\Mollie\Contracts\GetMollieCustomer;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;

class ManageChargesTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withPackageMigrations();
    }

    /** @test */
    public function usingMandatedChargeBuilderWhenValidMandate()
    {
        $owner = factory(User::class)->create();

        $this->assertInstanceOf(FirstPaymentChargeBuilder::class, $owner->newCharge());
    }

    /** @test */
    public function useNewMandatedCharge()
    {
        $this->withMockedGetMollieCustomer();
        $this->withMockedGetMollieMandate();
        $owner = $this->getMandatedUser(true, [
            'mollie_mandate_id' => 'mdt_unique_mandate_id',
            'mollie_customer_id' => 'cst_unique_customer_id',
        ]);

        $this->assertInstanceOf(MandatedChargeBuilder::class, $owner->newCharge());
    }

    protected function withMockedGetMollieMandate(): void
    {
        $this->mock(GetMollieMandate::class, function ($mock) {
            $mandate = new Mandate(new MollieApiClient);
            $mandate->id = 'mdt_unique_mandate_id';
            $mandate->status = 'valid';
            $mandate->method = 'directdebit';

            return $mock->shouldReceive('execute')->with('cst_unique_customer_id', 'mdt_unique_mandate_id')->once()->andReturn($mandate);
        });
    }

    protected function withMockedGetMollieCustomer(): void
    {
        $this->mock(GetMollieCustomer::class, function ($mock) {
            $customer = new Customer(new MollieApiClient);
            $customer->id = 'cst_unique_customer_id';

            return $mock->shouldReceive('execute')->with('cst_unique_customer_id')->once()->andReturn($customer);
        });
    }
}
