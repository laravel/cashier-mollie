<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Refunds;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\RefundProcessed;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Refunds\Refund;
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

        /** @var Refund $refund */
        $refund = factory(Refund::class)->create();
        $refund->items()->saveMany(
            RefundItemCollection::makeFromOrderItemCollection(
                factory(OrderItem::class, 2)->make()
            )->toArray()
        );
        $this->assertEquals(MollieRefundStatus::STATUS_PENDING, $refund->mollie_refund_status);

        $refund = $refund->handleProcessed();

        $this->assertNotNull($refund->order_id);
        $this->assertEquals(MollieRefundStatus::STATUS_REFUNDED, $refund->mollie_refund_status);

        /** @var \Laravel\Cashier\Order\Order $order */
        $order = $refund->order;
        $this->assertTrue($order->isProcessed());
        $this->assertEquals($refund->total, $order->total_due);

        // TODO Assert that refund is stored as orderable on order_item

        Event::assertDispatched(RefundProcessed::class, function (RefundProcessed $event) use ($refund) {
            return $event->refund->is($refund);
        });
    }
}
