<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Refunds;

use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Refunds\Refund;
use Laravel\Cashier\Refunds\RefundBuilder;
use Laravel\Cashier\Tests\BaseTestCase;

class RefundsBuilderTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withPackageMigrations();
    }

    /** @test */
    public function can_create_a_refund_for_a_complete_order(): void
    {
        $user = $this->getUser();

        $orderItems = $user->orderItems()->createMany([
            factory(OrderItem::class)->make()->toArray(),
            factory(OrderItem::class)->make()->toArray(),
        ]);

        $order = Order::createProcessedFromItems(new OrderItemCollection($orderItems));
        $order->mollie_payment_status = 'paid';
        $order->mollie_payment_id = 'tr_dummy_payment_id';

        $refundBuilder = RefundBuilder::forWholeOrder($order);
        $refund = $refundBuilder->create();

        // TODO assert local Refund created
        $this->assertInstanceOf(Refund::class, $refund);
        $this->assertEquals('re_dummy_refund_id', $refund->mollie_refund_id);
        // TODO assert Mollie refund created
        // TODO assert local Refund(Items) matches Order(items)
    }
}
