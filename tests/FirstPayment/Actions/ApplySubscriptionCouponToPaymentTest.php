<?php

namespace Laravel\Cashier\Tests\FirstPayment\Actions;

use Laravel\Cashier\Coupon\Coupon;
use Laravel\Cashier\Coupon\FixedDiscountHandler;
use Laravel\Cashier\FirstPayment\Actions\ActionCollection;
use Laravel\Cashier\FirstPayment\Actions\ApplySubscriptionCouponToPayment as Action;
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

        $this->coupon = new Coupon('test-coupon', new FixedDiscountHandler);
        $this->owner = factory(User::class)->make();
        $otherActions = new ActionCollection;

        $this->action = new Action($this->owner, $this->coupon, $otherActions);
    }

    /** @test */
    public function testGetSubtotalReturnsDiscountSubtotal()
    {
        $this->assertMoneyEURCents(-500, $this->action->getSubtotal());
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
