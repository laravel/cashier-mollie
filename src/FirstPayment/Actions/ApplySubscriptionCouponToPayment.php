<?php

namespace Laravel\Cashier\FirstPayment\Actions;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Coupon\Coupon;

class ApplySubscriptionCouponToPayment extends BaseNullAction
{
    /**
     * @var \Laravel\Cashier\Coupon\Coupon
     */
    protected $coupon;

    /**
     * @var \Laravel\Cashier\FirstPayment\Actions\ActionCollection
     */
    protected $otherActions;

    /**
     * ApplySubscriptionCouponToPayment constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param \Laravel\Cashier\Coupon\Coupon $coupon
     * @param \Laravel\Cashier\FirstPayment\Actions\ActionCollection $otherActions
     */
    public function __construct(Model $owner, Coupon $coupon, ActionCollection $otherActions)
    {
        $this->owner = $owner;
        $this->coupon = $coupon;
        $this->otherActions = $otherActions;
    }

    /**
     * @return \Money\Money
     */
    public function getSubtotal()
    {
        return $this->coupon->handler()->getFirstPaymentTotal($this->otherActions);
    }

    /**
     * @return \Money\Money
     */
    public function getTotal()
    {
        return $this->getSubtotal();
    }

    /**
     * @return \Money\Money
     */
    public function getTax()
    {
        return money(0, $this->getCurrency());
    }
}
