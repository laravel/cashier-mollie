<?php

namespace Laravel\Cashier\Coupon\Contracts;

use Laravel\Cashier\Coupon\Coupon;
use Laravel\Cashier\Coupon\RedeemedCoupon;
use Laravel\Cashier\Exceptions\CouponException;
use Laravel\Cashier\Order\OrderItemCollection;

interface CouponHandler
{
    /**
     * @param array $context
     * @return \Laravel\Cashier\Coupon\Contracts\CouponHandler
     */
    public function withContext(array $context);

    /**
     * @param \Laravel\Cashier\Coupon\Coupon $coupon
     * @param \Laravel\Cashier\Coupon\Contracts\AcceptsCoupons $model
     * @return bool
     * @throws \Throwable|CouponException
     */
    public function validate(Coupon $coupon, AcceptsCoupons $model);

    /**
     * Apply the coupon to the OrderItemCollection
     *
     * @param \Laravel\Cashier\Coupon\RedeemedCoupon $redeemedCoupon
     * @param \Laravel\Cashier\Order\OrderItemCollection $items
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function handle(RedeemedCoupon $redeemedCoupon, OrderItemCollection $items);

    /**
     * @param \Laravel\Cashier\Order\OrderItemCollection $items
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function getDiscountOrderItems(OrderItemCollection $items);
}
