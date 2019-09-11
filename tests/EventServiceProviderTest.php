<?php

namespace Laravel\Cashier\Tests;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\OrderInvoiceAvailable;
use Laravel\Cashier\Events\OrderPaymentPaid;
use Laravel\Cashier\Order\Order;

class EventServiceProviderTest extends BaseTestCase
{
    /** @test */
    public function itIsWiredUpAndFiring()
    {
        Event::fake(OrderInvoiceAvailable::class);

        $event = new OrderPaymentPaid(factory(Order::class)->make());
        Event::dispatch($event);

        Event::assertDispatched(OrderInvoiceAvailable::class, function($e) use ($event) {
            return $e->order === $event->order;
        });
    }
}
