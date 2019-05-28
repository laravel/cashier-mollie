<?php

namespace Laravel\Cashier\Tests\SubscriptionBuilder;

use Laravel\Cashier\Coupon\AppliedCoupon;
use Laravel\Cashier\Coupon\BaseCouponHandler;
use Laravel\Cashier\Coupon\Contracts\AcceptsCoupons;
use Laravel\Cashier\Coupon\Coupon;
use Laravel\Cashier\Coupon\RedeemedCoupon;
use Laravel\Cashier\Exceptions\CouponException;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\SubscriptionBuilder\MandatedSubscriptionBuilder;
use Laravel\Cashier\Tests\BaseTestCase;

class MandatedSubscriptionBuilderTest extends BaseTestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withPackageMigrations();
        $this->withConfiguredPlans();
        $this->user = $this->getCustomerUser(true);
    }

    /** @test */
    public function testWithCoupon()
    {
        $this->withMockedCouponRepository();

        $this->assertEquals(0, RedeemedCoupon::count());
        $this->assertEquals(0, AppliedCoupon::count());

        $builder = $this->getBuilder();

        $subscription = $builder->withCoupon('test-coupon')->create();

        $this->assertEquals(1, $subscription->redeemedCoupons()->count());

        // Coupons will be applied when preprocessing the Subscription OrderItems
        $this->assertEquals(0, $subscription->appliedCoupons()->count());
    }

    /** @test */
    public function testWithCouponValidatesCoupon()
    {
        $this->expectException(CouponException::class);
        $this->withMockedCouponRepository(null, new InvalidatingCouponHandler);
        $this->getBuilder()->withCoupon('test-coupon')->create();
    }

    /**
     * @return \Laravel\Cashier\SubscriptionBuilder\MandatedSubscriptionBuilder
     */
    protected function getBuilder()
    {
        return new MandatedSubscriptionBuilder(
            $this->user,
            'default',
            'monthly-10-1'
        );
    }
}

class InvalidatingCouponHandler extends BaseCouponHandler
{
    public function validate(Coupon $coupon, AcceptsCoupons $model)
    {
        throw new CouponException('This exception should be thrown');
    }

    public function getDiscountOrderItems(RedeemedCoupon $redeemedCoupon, OrderItemCollection $items)
    {
        return $items;
    }
}
