<?php

namespace Laravel\Cashier\Tests\Order;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\FirstPaymentPaid;
use Laravel\Cashier\Events\OrderInvoiceAvailable;
use Laravel\Cashier\Events\OrderPaymentPaid;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderInvoiceSubscriber;
use Laravel\Cashier\Tests\BaseTestCase;
use Mollie\Api\Resources\Payment;

class OrderInvoiceSubscriberTest extends BaseTestCase
{
    /**
     * @var \Laravel\Cashier\Order\OrderInvoiceSubscriber
     */
    private $subscriber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriber = new OrderInvoiceSubscriber;
    }

    /** @test */
    public function itHandlesTheFirstPaymentPaidEvent()
    {
        Event::fake(OrderInvoiceAvailable::class);
        $payment = $this->mock(Payment::class);
        $order = factory(Order::class)->make();
        $event = new FirstPaymentPaid($payment, $order);

        $this->subscriber->handleFirstPaymentPaid($event);

        Event::assertDispatched(OrderInvoiceAvailable::class, function($e) use ($order) {
            return $e->order === $order;
        });
    }

    /** @test */
    public function itHandlesTheOrderPaymentPaidEvent()
    {
        Event::fake(OrderInvoiceAvailable::class);
        $order = factory(Order::class)->make();
        $event = new OrderPaymentPaid($order);

        $this->subscriber->handleOrderPaymentPaid($event);

        Event::assertDispatched(OrderInvoiceAvailable::class, function($e) use ($order) {
            return $e->order === $order;
        });
    }
}
