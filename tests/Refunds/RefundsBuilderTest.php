<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Refunds;

use Laravel\Cashier\Mollie\Contracts\CreateMollieRefund;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Refunds\Refund;
use Laravel\Cashier\Refunds\RefundBuilder;
use Laravel\Cashier\Refunds\RefundItem;
use Laravel\Cashier\Tests\BaseTestCase;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Refund as MollieRefund;
use Mollie\Api\Types\RefundStatus as MollieRefundStatus;

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
        $this->mock(CreateMollieRefund::class, function (CreateMollieRefund $mock) {
            $mollieRefund = new MollieRefund(new MollieApiClient);
            $mollieRefund->id = 're_dummy_refund_id';
            $mollieRefund->status = MollieRefundStatus::STATUS_PENDING;
            $mock->shouldReceive('execute')->with('tr_dummy_payment_id', [
                'amount' => [
                    'value' => '22.00',
                    'currency' => 'EUR',
                ],
            ])->once()->andReturn($mollieRefund);
        });

        $user = $this->getUser();

        $orderItems = $user->orderItems()->createMany([
            factory(OrderItem::class)->make([
                'unit_price' => 1000,
                'tax_percentage' => 10,
                'quantity' => 1,
            ])->toArray(),
            factory(OrderItem::class)->make([
                'unit_price' => 500,
                'tax_percentage' => 10,
                'quantity' => 2,
            ])->toArray(),
        ]);

        $order = Order::createProcessedFromItems(new OrderItemCollection($orderItems));
        $order->mollie_payment_status = 'paid';
        $order->mollie_payment_id = 'tr_dummy_payment_id';
        $this->assertMoneyEURCents(2200, $order->getTotalDue());

        $refundBuilder = RefundBuilder::forWholeOrder($order);
        $refund = $refundBuilder->create();

        $this->assertInstanceOf(Refund::class, $refund);
        $this->assertEquals('re_dummy_refund_id', $refund->mollie_refund_id);
        $this->assertEquals(MollieRefundStatus::STATUS_PENDING, $refund->mollie_refund_status);
        $this->assertNull($refund->order_id);

        $refundItems = $refund->items;
        $this->assertCount(2, $refundItems);

        /** @var RefundItem $itemA */
        $itemA = $refundItems->first(function (RefundItem $item) {
            return (int) $item->quantity === 1;
        });

        $this->assertEquals($itemA->unit_price, 1000);
        $this->assertEquals($itemA->tax_percentage, 10);
        $this->assertEquals($itemA->currency, 'EUR');

        /** @var RefundItem $itemB */
        $itemB = $refundItems->first(function (RefundItem $item) {
            return (int) $item->quantity === 2;
        });
        $this->assertEquals($itemB->unit_price, 500);
        $this->assertEquals($itemB->tax_percentage, 10);
        $this->assertEquals($itemB->currency, 'EUR');
    }
}
