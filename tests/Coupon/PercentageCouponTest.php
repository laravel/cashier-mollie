<?php

namespace Laravel\Cashier\Tests\Coupon;

use Laravel\Cashier\Coupon\Contracts\CouponRepository;
use Laravel\Cashier\Coupon\Coupon;
use Laravel\Cashier\Coupon\CouponOrderItemPreprocessor;
use Laravel\Cashier\Coupon\PercentageDiscountHandler;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\BaseTestCase;

class PercentageCouponTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withPackageMigrations();
    }

    /** @test */
    public function couponCalculatesTheRightPrice()
    {
        $couponHandler = new PercentageDiscountHandler;

        $context = [
            'description' => 'Percentage coupon',
            'percentage' => 20,
        ];

        $coupon = new Coupon(
            'percentage-coupon',
            $couponHandler,
            $context
        );

        $this->withMockedCouponRepository($coupon, $couponHandler, $context);

        /** @var Subscription $subscription */
        $subscription = factory(Subscription::class)->create();
        $item = factory(OrderItem::class)->make();
        $subscription->orderItems()->save($item);

        /** @var \Laravel\Cashier\Coupon\Coupon $coupon */
        $coupon = app()->make(CouponRepository::class)->findOrFail('percentage-coupon');
        $redeemedCoupon = $coupon->redeemFor($subscription);
        $preprocessor = new CouponOrderItemPreprocessor();

        $result = $preprocessor->handle($item->toCollection());

        $this->assertEquals(-2952, $result[1]->unit_price);
    }
}
