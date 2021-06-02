<?php

namespace Laravel\Cashier\Tests\Charge;

use Laravel\Cashier\Http\RedirectToCheckoutResponse;
use Laravel\Cashier\Mollie\Contracts\CreateMollieCustomer;
use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Payment as MolliePayment;

class FirstPaymentChargeBuilderTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withPackageMigrations();
        $customer = new Customer(new MollieApiClient);
        $customer->id = 'cst_unique_customer_id';

        $this->mock(CreateMollieCustomer::class, function ($mock) use ($customer) {
            return $mock->shouldReceive('execute')
                ->andReturn($customer);
        });
    }

    /** @test */
    public function redirectToCheckoutResponse()
    {
        $this->withMockedCreateMolliePayment();
        $owner = factory(User::class)->create();
        $this->assertEquals(0, $owner->orderItems()->count());
        $this->assertEquals(0, $owner->orders()->count());

        $item = new \Laravel\Cashier\Charge\ChargeItemBuilder($owner);
        $item->unitPrice(money(100, 'EUR'));
        $item->description('Test Item');
        $chargeItem = $item->make();

        $item2 = new \Laravel\Cashier\Charge\ChargeItemBuilder($owner);
        $item2->unitPrice(money(200, 'EUR'));
        $item2->description('Test Item 2');
        $chargeItem2 = $item2->make();

        $builder = $owner->newCharge()
            ->addItem($chargeItem)
            ->addItem($chargeItem2)
            ->create();

        $this->assertEquals(0, $owner->orderItems()->count());
        $this->assertEquals(0, $owner->orders()->count());
        $this->assertInstanceOf(RedirectToCheckoutResponse::class, $builder);

    }

    protected function withMockedCreateMolliePayment(): void
    {
        $this->mock(CreateMolliePayment::class, function ($mock) {
            $payment = new MolliePayment(new MollieApiClient);
            $payment->id = 'tr_unique_payment_id';
            $payment->amount = (object) [
                'currency' => 'EUR',
                'value' => '3.00',
            ];
            $payment->_links = json_decode(json_encode([
                'checkout' => [
                    'href' => 'https://foo-redirect-bar.com',
                    'type' => 'text/html',
                ],
            ]));
            $payment->mandateId = 'mdt_dummy_mandate_id';

            return $mock->shouldReceive('execute')->once()->andReturn($payment);
        });
    }
}
