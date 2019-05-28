<?php

namespace Laravel\Cashier\Coupon\Contracts;

use Laravel\Cashier\Coupon\Coupon;
use Laravel\Cashier\Exceptions\CouponNotFoundException;

interface CouponRepository
{
    /**
     * @param string $coupon
     * @return Coupon|null
     */
    public function find(string $coupon);

    /**
     * @param string $coupon
     * @return Coupon
     *
     * @throws CouponNotFoundException
     */
    public function findOrFail(string $coupon);
}
