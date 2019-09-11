<?php

namespace Laravel\Cashier\Tests\Order;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\FirstPaymentPaid;
use Laravel\Cashier\Events\OrderInvoiceAvailable;
use Laravel\Cashier\Events\OrderPaymentPaid;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderInvoiceSubscriber;
use Laravel\Cashier\Tests\BaseTestCase;

class OrderInvoiceSubscriberTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriber = new OrderInvoiceSubscriber;
    }

    /** @test */
    public function itHandlesTheFirstPaymentPaidEvent()
    {
        $this->assertItHandlesEvent(FirstPaymentPaid::class, 'handleFirstPaymentPaid');
    }

    /** @test */
    public function itHandlesTheOrderPaymentPaidEvent()
    {
        $this->assertItHandlesEvent(OrderPaymentPaid::class, 'handleOrderPaymentPaid');
    }

    private function assertItHandlesEvent($eventClass, $methodName)
    {
        Event::fake(OrderInvoiceAvailable::class);
        $order = factory(Order::class)->make();
        $event = new $eventClass($order);

        (new OrderInvoiceSubscriber)->$methodName($event);

        Event::assertDispatched(OrderInvoiceAvailable::class, function($e) use ($order) {
            return $e->order === $order;
        });
    }
}
