<?php

namespace Laravel\Cashier\SubscriptionBuilder;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Coupon\Contracts\CouponRepository;
use Laravel\Cashier\Plan\Contracts\PlanRepository;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionBuilder\Contracts\SubscriptionBuilder as Contract;

/**
 * Creates and configures a subscription for an existing Mollie Mandate.
 */
class MandatedSubscriptionBuilder implements Contract
{
    /**
     * The model that is subscribing.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The name of the subscription.
     *
     * @var string
     */
    protected $name;

    /**
     * The quantity of the subscription.
     *
     * @var int
     */
    protected $quantity = 1;

    /**
     * The date and time the trial will expire.
     *
     * @var Carbon
     */
    protected $trialExpires;

    /**
     * When the first (next) payment should be processed once the subscription has been created.
     *
     * @var Carbon
     */
    protected $nextPaymentAt;

    /**
     * The Plan being subscribed to.
     *
     * @var \Laravel\Cashier\Plan\Plan
     */
    protected $plan;

    /** @var \Laravel\Cashier\Coupon\Coupon */
    protected $coupon;

    /** @var bool */
    protected $handleCoupon = true;

    /** @var bool */
    protected $validateCoupon = true;

    /**
     * Create a new subscription builder instance.
     *
     * @param mixed $owner
     * @param string $name
     * @param string $plan
     * @throws \Laravel\Cashier\Exceptions\PlanNotFoundException
     */
    public function __construct(Model $owner, string $name, string $plan)
    {
        $this->name = $name;
        $this->owner = $owner;
        $this->nextPaymentAt = Carbon::now();
        $this->plan = app(PlanRepository::class)::findOrFail($plan);
    }

    /**
     * Create a new Cashier subscription.
     *
     * @return Subscription
     * \Laravel\Cashier\Exceptions\CouponException
     * @throws \Laravel\Cashier\Exceptions\InvalidMandateException
     */
    public function create()
    {
        $this->owner->guardMollieMandate();
        $now = now();

        return DB::transaction(function () use ($now) {
            $subscription = $this->makeSubscription($now);
            $subscription->save();

            if ($this->coupon) {
                if ($this->validateCoupon) {
                    $this->coupon->validateFor($subscription);

                    if ($this->handleCoupon) {
                        $this->coupon->redeemFor($subscription);
                    }
                }
            }

            $subscription->scheduleNewOrderItemAt($this->nextPaymentAt);
            $subscription->save();

            $this->owner->cancelGenericTrial();

            return $subscription;
        });
    }

    /**
     * Prepare a not yet persisted Subscription model
     *
     * @param null|Carbon $now
     * @return Subscription $subscription
     */
    public function makeSubscription($now = null)
    {
        return $this->owner->subscriptions()->make([
            'name' => $this->name,
            'plan' => $this->plan->name(),
            'quantity' => $this->quantity,
            'tax_percentage' => $this->owner->taxPercentage() ?: 0,
            'trial_ends_at' => $this->trialExpires,
            'cycle_started_at' => $now ?: now(),
            'cycle_ends_at' => $this->nextPaymentAt,
        ]);
    }

    /**
     * Specify the number of days of the trial.
     *
     * @param  int  $trialDays
     * @return $this
     */
    public function trialDays(int $trialDays)
    {
        return $this->trialUntil(now()->addDays($trialDays));
    }

    /**
     * Specify the ending date of the trial.
     *
     * @param  Carbon  $trialUntil
     * @return $this
     */
    public function trialUntil(Carbon $trialUntil)
    {
        $this->trialExpires = $trialUntil;
        $this->nextPaymentAt = $trialUntil;

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * @return \Laravel\Cashier\SubscriptionBuilder\Contracts\SubscriptionBuilder|void
     */
    public function skipTrial()
    {
        $this->trialExpires = null;
        $this->nextPaymentAt = now();

        return $this;
    }

    /**
     * Specify the quantity of the subscription.
     *
     * @param  int  $quantity
     * @return $this
     */
    public function quantity(int $quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Specify a coupon.
     *
     * @param string $coupon
     * @return $this|\Laravel\Cashier\SubscriptionBuilder\Contracts\SubscriptionBuilder
     * @throws \Laravel\Cashier\Exceptions\CouponNotFoundException
     */
    public function withCoupon(string $coupon)
    {
        /** @var CouponRepository $repository */
        $repository = app()->make(CouponRepository::class);
        $this->coupon = $repository->findOrFail($coupon);

        return $this;
    }

    /**
     * Override the default next payment date. This is superseded by the trial end date.
     *
     * @param \Carbon\Carbon $nextPaymentAt
     * @return MandatedSubscriptionBuilder
     */
    public function nextPaymentAt(Carbon $nextPaymentAt)
    {
        $this->nextPaymentAt = $nextPaymentAt;

        return $this;
    }

    /**
     * Skip validating the coupon when creating the subscription.
     *
     * @return $this
     */
    public function skipCouponValidation()
    {
        $this->validateCoupon = false;

        return $this;
    }

    /**
     * Skip handling the coupon completely when creating the subscription.
     *
     * @return $this
     */
    public function skipCouponHandling()
    {
        $this->handleCoupon = false;

        return $this;
    }
}
