<?php

namespace Laravel\Cashier\Tests\OneOffPayment;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\OrderCreated;
use Laravel\Cashier\Events\OrderProcessed;
use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment;
use Laravel\Cashier\Mollie\Contracts\GetMollieCustomer;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate;
use Laravel\Cashier\Mollie\Contracts\GetMollieMethodMinimumAmount;
use Laravel\Cashier\Order\Invoice;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Payment as MolliePayment;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ManagesInvoicesTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withPackageMigrations();
    }

    /** @test */
    public function canFindInvoices()
    {
        $user = $this->getCustomerUser();
        $items = factory(OrderItem::class, 2)
            ->states(['EUR'])
            ->create([
                'owner_id' => $user->getKey(),
                'owner_type' => $user->getMorphClass(),
                'unit_price' => 12150,
                'quantity' => 1,
                'tax_percentage' => 21.5,
                'orderable_type' => null,
                'orderable_id' => null,
            ]);
        $order = Order::createFromItems($items);

        $createdInvoice = $order->fresh()->invoice();
        $foundInvoice = $user->findInvoice($order->id);

        $this->assertEquals($createdInvoice, $foundInvoice);
        $this->assertNull($user->findInvoice('non-existing-order'));
    }

    /** @test */
    public function canFindInvoiceOrFail()
    {
        $user = $this->getCustomerUser();
        $items = factory(OrderItem::class, 2)
            ->states(['unlinked', 'EUR'])
            ->create([
                'owner_id' => $user->id,
                'owner_type' => User::class,
                'unit_price' => 12150,
                'quantity' => 1,
                'tax_percentage' => 21.5,
            ]);
        $order = Order::createFromItems($items, [
            'balance_before' => 500,
            'credit_used' => 500,
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
        ]);

        $createdInvoice = $order->fresh()->invoice();
        $foundInvoice = $user->findInvoiceOrFail($order->id);

        $this->assertEquals($createdInvoice, $foundInvoice);

        $this->expectException(NotFoundHttpException::class);
        $this->assertNull($user->findInvoiceOrFail('non-existing-order'));
    }

    /** @test */
    public function canAddSomethingOnAUsersTab()
    {
        $customerUser = $this->getMandatedUser();

        $orderItem = $customerUser->tab('A potato', 100, [
            'currency' => $currency = 'eur',
        ]);

        $this->assertInstanceOf(OrderItem::class, $orderItem);
        $this->assertTrue($orderItem->owner->is($customerUser));
        $this->assertSame($orderItem->getCurrency(), 'EUR');
    }

    /** @test */
    public function canInvoiceOpenTabWhenHavingAMandate()
    {
        Event::fake();

        $this->withMockedGetMollieMandate(4);
        $this->withMockedGetMollieCustomer(4);
        $this->withMockeGetMollieMethodMinimumAmount();
        $this->withMockedCreateMolliePayment();

        $mandatedCustomer = $this->getMandatedUser();

        $this->assertTrue($mandatedCustomer->validMollieMandate());

        // We put something on the tab in EUR
        $mandatedCustomer->tab('A potato', 1000);

        // We have an order
        $order = $mandatedCustomer->invoice();

        // Order got processed
        $this->assertTrue($order->isProcessed());
        $this->assertTrue($mandatedCustomer->validMollieMandate());
        $this->assertInstanceOf(Order::class, $order);
        Event::assertDispatched(OrderCreated::class, function ($e) use ($order) {
            return $e->order->is($order);
        });
        Event::assertDispatched(OrderProcessed::class, function (OrderProcessed $event) use ($order) {
            return $event->order->is($order);
        });
    }

    /** @test */
    public function canInvoiceForWithAValidMandate()
    {
        Event::fake();

        $this->withMockedGetMollieMandate(2);
        $this->withMockedGetMollieCustomer(2);
        $this->withMockeGetMollieMethodMinimumAmount();
        $this->withMockedCreateMolliePayment();

        $mandatedUser = $this->getMandatedUser();

        $createdOrder = $mandatedUser->invoiceFor('A potato', 100);

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
    public function canOverrideCurrencyWhenUsingInvoiceFor()
    {
        Event::fake();
        $this->withMockedGetMollieMandate(2);
        $this->withMockedGetMollieCustomer(2);
        $this->withMockeGetMollieMethodMinimumAmount();
        $this->withMockedCreateMolliePayment();

        $mandatedUser = $this->getMandatedUser();

        $createdOrder = $mandatedUser->invoiceFor('A potato', 100, [
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

    /** @test */
    public function canGetTheUpcomingInvoice()
    {
        $user = $this->getUser(true, ['tax_percentage' => 10]);
        $user->tab('A premium quality potato', 1000);
        $user->tab('A high quality carrot', 800);

        $inMemoryOrder = $user->upcomingInvoice(['number' => 'Concept']);

        $this->assertFalse($inMemoryOrder->exists);
        $this->assertFalse($inMemoryOrder->isProcessed());
        $this->assertSame('Concept', $inMemoryOrder->number);
        $this->assertMoneyEURCents(180, $inMemoryOrder->getTax());
        $this->assertMoneyEURCents(1800, $inMemoryOrder->getSubtotal());
        $this->assertMoneyEURCents(1980, $inMemoryOrder->getTotal());
        $this->assertInstanceOf(Invoice::class, $inMemoryOrder->invoice());

        $firstItem = $inMemoryOrder->items->first();
        $secondItem = $inMemoryOrder->items->last();
        $this->assertInstanceOf(OrderItem::class, $firstItem);
        $this->assertInstanceOf(OrderItem::class, $secondItem);
        $this->assertSame('A premium quality potato', $firstItem->description);
        $this->assertSame('A high quality carrot', $secondItem->description);
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
            $payment = new Payment(new MollieApiClient);
            $payment->id = 'tr_unique_payment_id';
            $payment->amount = (object) [
                'currency' => 'EUR',
                'value' => '10.00',
            ];
            $payment->mandateId = 'mdt_dummy_mandate_id';

            return $mock->shouldReceive('execute')
                ->once()
                ->andReturn($payment);
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

    protected function withMockeGetMollieMethodMinimumAmount()
    {
        $this->mock(GetMollieMethodMinimumAmount::class, function ($mock) {
            return $mock->shouldReceive('execute')
                ->with('directdebit', 'EUR')
                ->once()
                ->andReturn(money(10, 'EUR'));
        });
    }
}
