<?php

namespace Laravel\Cashier\Events;

use Laravel\Cashier\Coupon\AppliedCoupon;
use Laravel\Cashier\Coupon\RedeemedCoupon;

class CouponApplied
{
    /**
     * @var \Laravel\Cashier\Coupon\RedeemedCoupon
     */
    public $redeemedCoupon;

    /**
     * @var \Laravel\Cashier\Coupon\AppliedCoupon
     */
    public $appliedCoupon;

    public function __construct(RedeemedCoupon $redeemedCoupon, AppliedCoupon $appliedCoupon)
    {
        $this->redeemedCoupon = $redeemedCoupon;
        $this->appliedCoupon = $appliedCoupon;
    }
}
