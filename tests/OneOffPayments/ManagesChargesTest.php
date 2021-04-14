<?php

namespace Laravel\Cashier\Tests\OneOffPayments;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\OrderCreated;
use Laravel\Cashier\Events\OrderProcessed;
use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment;
use Laravel\Cashier\Mollie\Contracts\GetMollieCustomer;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate;
use Laravel\Cashier\Mollie\Contracts\GetMollieMethodMinimumAmount;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Tests\BaseTestCase;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;
use Mollie\Api\Resources\Payment as MolliePayment;

class ManagesChargesTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withPackageMigrations();
    }

    /** @test */
    public function canChargeForWithAValidMandate()
    {
        Event::fake();

        $this->withMockedGetMollieMandate(2);
        $this->withMockedGetMollieCustomer(2);
        $this->withMockedGetMollieMethodMinimumAmount();
        $this->withMockedCreateMolliePayment();

        $mandatedUser = $this->getMandatedUser();

        $createdOrder = $mandatedUser->chargeFor('A potato', 100);

        $this->assertInstanceOf(Order::class, $createdOrder);
        $this->assertMoneyEURCents(100, $createdOrder->getTotal());

        // We have the associated order.
        /** @var Order $order */
        $foundOrder = $mandatedUser->orders->first();
        $this->assertInstanceOf(Order::class, $foundOrder);
        $this->assertMoneyEURCents(100, $foundOrder->getTotal());
        $this->assertEquals($foundOrder, $createdOrder->fresh());
        Event::assertDispatched(OrderCreated::class, function ($e) use ($createdOrder) {
            return $e->order->is($createdOrder);
        });
        Event::assertDispatched(OrderProcessed::class, function (OrderProcessed $event) use ($createdOrder) {
            return $event->order->is($createdOrder);
        });
    }

    /** @test */
    public function canOverrideCurrencyWhenUsingChargeFor()
    {
        Event::fake();
        $this->withMockedGetMollieMandate(2);
        $this->withMockedGetMollieCustomer(2);
        $this->withMockedGetMollieMethodMinimumAmount();
        $this->withMockedCreateMolliePayment();

        $mandatedUser = $this->getMandatedUser();

        $createdOrder = $mandatedUser->chargeFor('A potato', 100, [
            'currency' => 'eur', // non default currency
        ]);
        $this->assertInstanceOf(Order::class, $createdOrder);
        $this->assertMoneyEURCents(100, $createdOrder->getTotal());


        /** @var Order $order */
        $foundOrder = $mandatedUser->orders->first();
        $this->assertInstanceOf(Order::class, $foundOrder);
        $this->assertMoneyEURCents(100, $foundOrder->getTotal());
        $this->assertEquals($foundOrder, $createdOrder->fresh());
        Event::assertDispatched(OrderCreated::class, function ($e) use ($createdOrder) {
            return $e->order->is($createdOrder);
        });
        Event::assertDispatched(OrderProcessed::class, function (OrderProcessed $event) use ($createdOrder) {
            return $event->order->is($createdOrder);
        });

        Cashier::useCurrency('eur'); // reset
    }

    protected function withMockedGetMollieCustomer($times = 1)
    {
        $this->mock(GetMollieCustomer::class, function ($mock) use ($times) {
            $customer = new Customer(new MollieApiClient);
            $customer->id = 'cst_unique_customer_id';

            return $mock->shouldReceive('execute')
                ->with('cst_unique_customer_id')
                ->times($times)
                ->andReturn($customer);
        });
    }

    protected function withMockedCreateMolliePayment(): void
    {
        $this->mock(CreateMolliePayment::class, function ($mock) {
            $payment = new MolliePayment(new MollieApiClient);
            $payment->id = 'tr_unique_payment_id';
            $payment->amount = (object) [
                'currency' => 'EUR',
                'value' => '10.00',
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

    protected function withMockedGetMollieMandate($times = 1): void
    {
        $this->mock(GetMollieMandate::class, function ($mock) use ($times) {
            $mandate = new Mandate(new MollieApiClient);
            $mandate->id = 'mdt_unique_mandate_id';
            $mandate->status = 'valid';
            $mandate->method = 'directdebit';

            return $mock->shouldReceive('execute')
                ->with('cst_unique_customer_id', 'mdt_unique_mandate_id')
                ->times($times)
                ->andReturn($mandate);
        });
    }

    protected function withMockedGetMollieMethodMinimumAmount()
    {
        $this->mock(GetMollieMethodMinimumAmount::class, function ($mock) {
            return $mock->shouldReceive('execute')
                ->with('directdebit', 'EUR')
                ->once()
                ->andReturn(money(10, 'EUR'));
        });
    }
}
