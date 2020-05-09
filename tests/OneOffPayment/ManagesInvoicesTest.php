<?php

namespace Laravel\Cashier\Tests\OneOffPayment;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\OrderCreated;
use Laravel\Cashier\Events\OrderPaymentFailed;
use Laravel\Cashier\Events\OrderPaymentPaid;
use Laravel\Cashier\Events\OrderProcessed;
use Laravel\Cashier\Order\Invoice;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Mollie\Api\Resources\Payment;
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
            'currency' => $currency = 'usd',
        ]);

        $this->assertInstanceOf(OrderItem::class, $orderItem);
        $this->assertTrue($orderItem->owner->is($customerUser));
        $this->assertSame($orderItem->getCurrency(), 'USD');
    }

    /** @test */
    public function canInvoiceOpenTabWithoutAMandate()
    {
        Event::fake();

        // To prove it takes the default currency when invoicing by default.
        Cashier::useCurrency('usd');

        $customerUser = $this->getCustomerUser();

        // We put something on the tab in EUR
        $customerUser->tab('A potato', 100, [
            'currency' => $currency = 'eur',
        ]);

        // The default currency is 'USD', we don't have any open tab for the default currency
        $createdOrder = $customerUser->invoice();
        $this->assertFalse($createdOrder);

        // We get a redirect to pay for the invoice.
        $response = $customerUser->invoice(['currency' => $currency]);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertInstanceOf(RedirectToCheckoutResponse::class, $response);
        $this->assertInstanceOf(Payment::class, $response->payment());
        $this->assertFalse($customerUser->validMollieMandate());

        Event::assertNotDispatched(OrderProcessed::class);
        Event::assertNotDispatched(OrderCreated::class);

        Cashier::useCurrency('eur'); // reset

        // Use this payment ID after checking out at the URL:
        // - set status to paid > test the successful webhook test.
        // - set status to failed > test the failed webhook test.
        // dd(
        //     $response->payment()->id,
        //     $response->payment()->_links->checkout
        // );
    }

    /** @test */
    public function canInvoiceOpenTabWhenHavingAMandate()
    {
        Event::fake();

        $mandatedCustomer = $this->getMandatedUser();
        $this->assertTrue($mandatedCustomer->validMollieMandate());

        // We put something on the tab in EUR
        $mandatedCustomer->tab('A potato', 100);

        // We have an order
        $order = $mandatedCustomer->invoice();

        // Order got processed
        $this->assertTrue($order->isProcessed());
        $this->assertTrue($mandatedCustomer->validMollieMandate());
        $this->assertInstanceOf(Order::class, $order);
        Event::assertDispatched(OrderCreated::class, function ($e) use ($order) {
            return $e->order->is($order);
        });
        Event::assertDispatched(OrderProcessed::class, function(OrderProcessed $event) use ($order) {
            return $event->order->is($order);
        });
    }

    /** @test */
    public function canInvoiceForWithAValidMandate()
    {
        Event::fake();
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
        Event::assertDispatched(OrderProcessed::class, function(OrderProcessed $event) use ($createdOrder) {
            return $event->order->is($createdOrder);
        });
    }

    /** @test */
    public function canOverrideCurrencyWhenUsingInvoiceFor()
    {
        Event::fake();
        Cashier::useCurrency('usd');

        $mandatedUser = $this->getMandatedUser();

        // We wish to create an invoice for a non default currency
        // The invoice should automatically be made in the appropriate currency,
        // even if the user doesn't specify it in the payment options.
        $createdOrder = $mandatedUser->invoiceFor('A potato', 100, [
            'currency' => 'eur' // non default currency
        ]);
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
        Event::assertDispatched(OrderProcessed::class, function(OrderProcessed $event) use ($createdOrder) {
            return $event->order->is($createdOrder);
        });

        Cashier::useCurrency('eur'); // reset
    }

    /** @test */
    public function handlesSuccessfulOneOffPaymentWebhookEvents()
    {
        Event::fake();
        $this->withoutExceptionHandling();
        $user = $this->getUser();
        $orderItem = factory(OrderItem::class)->create();

        $response = $this->post(
            route('webhooks.mollie.one_off_payment', ['id' => $paymentPaidId = env('PAYMENT_PAID_ID')])
        );

        $order = $orderItem->fresh()->order;
        $response->assertStatus(200);

        Event::assertDispatched(OrderCreated::class, function ($e) use ($order) {
            return $e->order->is($order);
        });
        Event::assertDispatched(OrderProcessed::class, function(OrderProcessed $event) use ($order) {
            return $event->order->is($order);
        });
        Event::assertDispatched(OrderPaymentPaid::class, function(OrderPaymentPaid $event) use ($order) {
            return $event->order->is($order);
        });
    }

    /** @test */
    public function handlesFailedOneOffPaymentWebhookEvents()
    {
        Event::fake();
        $this->withoutExceptionHandling();
        $order = factory(Order::class)->create([
            // @see ManagesInvoicesTest@canInvoiceOpenTabWithoutAMandate
            'mollie_payment_id' => $failedPaymentId = env('PAYMENT_FAILED_ID')
        ]);

        $this->getMandatedUser();

        $response = $this->post(
            route('webhooks.mollie.default', ['id' => $failedPaymentId])
        );

        $response->assertStatus(200);

        Event::assertDispatched(OrderPaymentFailed::class, function(OrderPaymentFailed $event) use ($order) {
            return $event->order->is($order);
        });
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
}
