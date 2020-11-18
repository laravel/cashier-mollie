<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Refunds;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\RefundProcessed;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Refunds\Refund;
use Laravel\Cashier\Refunds\RefundItem;
use Laravel\Cashier\Refunds\RefundItemCollection;
use Laravel\Cashier\Tests\BaseTestCase;
use Mollie\Api\Types\RefundStatus as MollieRefundStatus;

class RefundTest extends BaseTestCase
{
    /** @test */
    public function canHandleProcessedMollieRefund()
    {
        Event::fake();
        $this->withPackageMigrations();

        $user = $this->getCustomerUser();
        $originalOrderItems = factory(OrderItem::class, 2)->create();
        $originalOrder = Order::createProcessedFromItems($originalOrderItems);

        /** @var Refund $refund */
        $refund = factory(Refund::class)->create([
            'total' => 29524,
            'currency' => 'EUR',
        ]);

        $refund->items()->saveMany(
            RefundItemCollection::makeFromOrderItemCollection($originalOrderItems)
        );
        $this->assertEquals(MollieRefundStatus::STATUS_PENDING, $refund->mollie_refund_status);

        $refund = $refund->handleProcessed();

        $this->assertNotNull($refund->order_id);
        $this->assertEquals(MollieRefundStatus::STATUS_REFUNDED, $refund->mollie_refund_status);

        $order = $refund->order;
        $this->assertTrue($order->isNot($originalOrder));
        $this->assertTrue($order->isProcessed());
        $this->assertEquals(-29524, $order->total_due);
        $this->assertInstanceOf(RefundItem::class, $order->items->first()->orderable);

        Event::assertDispatched(RefundProcessed::class, function (RefundProcessed $event) use ($refund) {
            return $event->refund->is($refund);
        });
    }
}
