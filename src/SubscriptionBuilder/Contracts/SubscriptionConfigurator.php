<?php

namespace Laravel\Cashier\SubscriptionBuilder\Contracts;

use Carbon\Carbon;

interface SubscriptionConfigurator
{
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
     * Force the trial to end immediately.
     *
     * @return $this
     */
    public function skipTrial();

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
