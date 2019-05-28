<?php

namespace Laravel\Cashier\SubscriptionBuilder\Contracts;

use Carbon\Carbon;

interface SubscriptionBuilder
{
    /**
     * Create a new Cashier subscription. Returns a redirect to checkout if necessary.
     *
     * @return mixed
     */
    public function create();

    /**
     * Specify the number of days of the trial.
     *
     * @param  int $trialDays
     * @return $this
     */
    public function trialDays(int $trialDays);

    /**
     * Specify the ending date of the trial.
     *
     * @param  Carbon $trialUntil
     * @return $this
     */
    public function trialUntil(Carbon $trialUntil);

    /**
     * Override the default next payment date.
     *
     * @param \Carbon\Carbon $nextPaymentAt
     * @return $this
     */
    public function nextPaymentAt(Carbon $nextPaymentAt);

    /**
     * Specify the quantity of the subscription.
     *
     * @param  int  $quantity
     * @return $this
     */
    public function quantity(int $quantity);

    /**
     * Specify a discount coupon.
     *
     * @param string $coupon
     * @return $this
     */
    public function withCoupon(string $coupon);
}
