<?php

namespace Laravel\Cashier\Tests\Coupon;

use Laravel\Cashier\Coupon\AppliedCoupon;
use Laravel\Cashier\Coupon\Contracts\CouponRepository;
use Laravel\Cashier\Coupon\CouponOrderItemPreprocessor;
use Laravel\Cashier\Coupon\RedeemedCoupon;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\BaseTestCase;

class CouponOrderItemPreprocessorTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withPackageMigrations();
    }

    /** @test */
    public function appliesCoupon()
    {
        $this->withMockedCouponRepository();

        /** @var Subscription $subscription */
        $subscription = factory(Subscription::class)->create();
        $item = factory(OrderItem::class)->make();
        $subscription->orderItems()->save($item);

        /** @var \Laravel\Cashier\Coupon\Coupon $coupon */
        $coupon = app()->make(CouponRepository::class)->findOrFail('test-coupon');
        $redeemedCoupon = $coupon->redeemFor($subscription);
        $preprocessor = new CouponOrderItemPreprocessor();
        $this->assertEquals(0, AppliedCoupon::count());
        $this->assertEquals(1, $redeemedCoupon->times_left);

        $result = $preprocessor->handle($item->toCollection());

        $this->assertEquals(1, AppliedCoupon::count());
        $this->assertInstanceOf(OrderItemCollection::class, $result);
        $this->assertNotEquals($item->toCollection(), $result);
        $this->assertEquals(0, $redeemedCoupon->refresh()->times_left);
    }

    /** @test */
    public function passesThroughWhenNoRedeemedCoupon()
    {
        $preprocessor = new CouponOrderItemPreprocessor();
        $items = factory(OrderItem::class, 1)->make();
        $this->assertInstanceOf(OrderItemCollection::class, $items);
        $this->assertEquals(0, RedeemedCoupon::count());

        $result = $preprocessor->handle($items);

        $this->assertInstanceOf(OrderItemCollection::class, $result);
        $this->assertEquals($items, $result);
    }
}
