<?php

namespace Laravel\Cashier\Tests\FirstPayment\Actions;

use Laravel\Cashier\Coupon\Contracts\CouponRepository;
use Laravel\Cashier\FirstPayment\Actions\ApplySubscriptionCouponToPayment as Action;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;

class ApplySubscriptionCouponToPaymentTest extends BaseTestCase
{
    private $action;
    private $coupon;
    private $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withMockedCouponRepository();
        $this->coupon = app()->make(CouponRepository::class)->findOrFail('test-coupon');
        $this->owner = factory(User::class)->make();
        $orderItems = factory(OrderItem::class)->make([
            'unit_price' => 10000,
            'currency' => 'EUR',
        ])->toCollection();

        $this->action = new Action($this->owner, $this->coupon, $orderItems);
    }

    /** @test */
    public function testGetTotalReturnsDiscountSubtotal()
    {
        $this->assertMoneyEURCents(-500, $this->action->getTotal());
    }

    /** @test */
    public function testTaxDefaultsToZero()
    {
        $this->assertEquals(0, $this->action->getTaxPercentage());
        $this->assertMoneyEURCents(0, $this->action->getTax());
    }

    /** @test */
    public function testCreateFromPayloadReturnsNull()
    {
        $this->assertNull(Action::createFromPayload(['foo' => 'bar'], factory(User::class)->make()));
    }

    /** @test */
    public function testGetPayloadReturnsNull()
    {
        $this->assertNull($this->action->getPayload());
    }

    /** @test */
    public function testExecuteReturnsEmptyOrderItemCollection()
    {
        $result = $this->action->execute();
        $this->assertEquals(new OrderItemCollection, $result);
    }
}
