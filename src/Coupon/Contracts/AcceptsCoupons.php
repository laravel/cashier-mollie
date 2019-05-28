<?php

namespace Laravel\Cashier\Coupon\Contracts;

interface AcceptsCoupons
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function redeemedCoupons();

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function appliedCoupons();

    /**
     * @return string
     */
    public function ownerType();

    /**
     * @return mixed
     */
    public function ownerId();
}
