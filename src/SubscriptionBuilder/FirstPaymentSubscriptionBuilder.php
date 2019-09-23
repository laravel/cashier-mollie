<?php

namespace Laravel\Cashier\SubscriptionBuilder;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Coupon\NullCouponAcceptor;
use Laravel\Cashier\Coupon\RedeemedCoupon;
use Laravel\Cashier\FirstPayment\Actions\ActionCollection;
use Laravel\Cashier\FirstPayment\Actions\AddGenericOrderItem;
use Laravel\Cashier\FirstPayment\Actions\ApplySubscriptionCouponToPayment;
use Laravel\Cashier\FirstPayment\Actions\StartSubscription;
use Laravel\Cashier\FirstPayment\FirstPaymentBuilder;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Plan\Contracts\PlanRepository;
use Laravel\Cashier\Plan\Plan;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionBuilder\Contracts\SubscriptionBuilder as Contract;

/**
 * Creates and configures a Mollie first payment to create a new mandate via Mollie's checkout
 * and start a new subscription. If the subscription has a leading trial period, the payment amount is reduced to
 * an amount defined on the subscription plan.
 */
class FirstPaymentSubscriptionBuilder implements Contract
{
    /**
     * @var \Laravel\Cashier\FirstPayment\FirstPaymentBuilder
     */
    protected $firstPaymentBuilder;

    /**
     * @var \Laravel\Cashier\FirstPayment\Actions\StartSubscription
     */
    protected $startSubscription;

    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var Plan
     */
    protected $plan;

    /**
     * Whether this payment results in a subscription trial
     *
     * @var bool
     */
    protected $isTrial = false;

    /**
     * Create a new subscription builder instance.
     *
     * @param mixed $owner
     * @param string $name
     * @param string $plan
     */
    public function __construct(Model $owner, string $name, string $plan)
    {
        $this->owner = $owner;
        $this->name = $name;

        $this->plan = app(PlanRepository::class)::findOrFail($plan);

        $this->firstPaymentBuilder = new FirstPaymentBuilder($owner);
        $this->firstPaymentBuilder->setFirstPaymentMethod($this->plan->firstPaymentMethod());

        $this->startSubscription = new StartSubscription($owner, $name, $plan);
    }

    /**
     * Create a new subscription. Returns a redirect to Mollie's checkout screen.
     *
     * @return \Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse
     * @throws \Laravel\Cashier\Exceptions\CouponException|\Throwable
     */
    public function create()
    {
        $this->validateCoupon();

        $actions = new ActionCollection([$this->startSubscription]);
        $coupon = $this->startSubscription->coupon();

        if($this->isTrial) {
            $taxPercentage = $this->owner->taxPercentage() * 0.01;
            $total = $this->plan->firstPaymentAmount();

            $vat = $total->divide(1 + $taxPercentage)->multiply($taxPercentage);
            $subtotal = $total->subtract($vat);

            $actions[] = new AddGenericOrderItem(
                $this->owner,
                $subtotal,
                $this->plan->firstPaymentDescription()
            );
        } elseif ($coupon) {
            $actions[] = new ApplySubscriptionCouponToPayment($this->owner, $coupon, $actions);
        }

        $this->firstPaymentBuilder->inOrderTo($actions->toArray())->create();

        return $this->redirectToCheckout();
    }

    /**
     * Specify the number of days of the trial.
     *
     * @param  int $trialDays
     * @return $this
     * @throws \Laravel\Cashier\Exceptions\PlanNotFoundException
     * @throws \Throwable
     */
    public function trialDays(int $trialDays)
    {
        return $this->trialUntil(Carbon::now()->addDays($trialDays));
    }

    /**
     * Specify the ending date of the trial.
     *
     * @param  Carbon $trialUntil
     * @return $this
     * @throws \Laravel\Cashier\Exceptions\PlanNotFoundException
     * @throws \Throwable
     */
    public function trialUntil(Carbon $trialUntil)
    {
        $this->startSubscription->trialUntil($trialUntil);
        $this->isTrial = true;

        return $this;
    }

    /**
     * Specify the quantity of the subscription.
     *
     * @param  int $quantity
     * @return $this
     * @throws \Throwable|\LogicException
     */
    public function quantity(int $quantity)
    {
        throw_if($quantity < 1, new \LogicException('Subscription quantity must be at least 1.'));
        $this->startSubscription->quantity($quantity);

        return $this;
    }

    /**
     * Specify a discount coupon.
     *
     * @param string $coupon
     * @return $this
     * @throws \Laravel\Cashier\Exceptions\CouponNotFoundException
     */
    public function withCoupon(string $coupon)
    {
        $this->startSubscription->withCoupon($coupon);

        return $this;
    }

    /**
     * Override the default next payment date. This is superseded by the trial end date.
     *
     * @param \Carbon\Carbon $nextPaymentAt
     * @return $this
     */
    public function nextPaymentAt(Carbon $nextPaymentAt)
    {
        $this->startSubscription->nextPaymentAt($nextPaymentAt);

        return $this;
    }

    /**
     * @return \Laravel\Cashier\FirstPayment\FirstPaymentBuilder
     */
    public function getMandatePaymentBuilder()
    {
        return $this->firstPaymentBuilder;
    }

    /**
     * @return \Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse
     */
    protected function redirectToCheckout()
    {
        return RedirectToCheckoutResponse::forFirstPaymentSubscriptionBuilder($this);
    }

    /**
     * @throws \Laravel\Cashier\Exceptions\CouponException|\Throwable
     */
    protected function validateCoupon()
    {
        $coupon = $this->startSubscription->coupon();

        if ($coupon) {
            $coupon->validateFor(
                $this->startSubscription->builder()->makeSubscription()
            );
        }
    }
}
