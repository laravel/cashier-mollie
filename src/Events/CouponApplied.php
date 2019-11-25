<?php

namespace Laravel\Cashier\Events;

use Illuminate\Queue\SerializesModels;
use Laravel\Cashier\Coupon\AppliedCoupon;
use Laravel\Cashier\Coupon\RedeemedCoupon;

class CouponApplied
{
    use SerializesModels;

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
