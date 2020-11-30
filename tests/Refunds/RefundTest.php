<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Refunds;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\RefundFailed;
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
        $this->assertMoneyEURCents(0, $originalOrder->getAmountRefunded());

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
        $this->assertMoneyEURCents(29524, $originalOrder->refresh()->getAmountRefunded());

        $order = $refund->order;
        $this->assertTrue($order->isNot($originalOrder));
        $this->assertTrue($order->isProcessed());
        $this->assertEquals(-29524, $order->total_due);
        $this->assertInstanceOf(RefundItem::class, $order->items->first()->orderable);

        Event::assertDispatched(RefundProcessed::class, function (RefundProcessed $event) use ($refund) {
            return $event->refund->is($refund);
        });
    }

    /** @test */
    public function canHandleFailedMollieRefund()
    {
        Event::fake();
        $this->withPackageMigrations();

        $user = $this->getCustomerUser();
        $originalOrderItems = factory(OrderItem::class, 2)->create();
        $originalOrder = Order::createProcessedFromItems($originalOrderItems);
        $this->assertMoneyEURCents(0, $originalOrder->getAmountRefunded());

        /** @var Refund $refund */
        $refund = factory(Refund::class)->create([
            'total' => 29524,
            'currency' => 'EUR',
        ]);

        $refund->items()->saveMany(
            RefundItemCollection::makeFromOrderItemCollection($originalOrderItems)
        );
        $this->assertEquals(MollieRefundStatus::STATUS_PENDING, $refund->mollie_refund_status);

        $refund = $refund->handleFailed();

        $this->assertNull($refund->order_id);
        $this->assertEquals(MollieRefundStatus::STATUS_FAILED, $refund->mollie_refund_status);
        $this->assertMoneyEURCents(0, $originalOrder->refresh()->getAmountRefunded());

        $this->assertNull($refund->order);

        Event::assertDispatched(RefundFailed::class, function (RefundFailed $event) use ($refund) {
            return $event->refund->is($refund);
        });
    }
}
