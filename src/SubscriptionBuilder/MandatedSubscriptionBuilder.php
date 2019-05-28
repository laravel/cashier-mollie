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
     * @var integer
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
     */
    public function create()
    {
        $now = now();

        return DB::transaction(function () use ($now) {
            /** @var Subscription $subscription */
            $subscription = $this->owner->subscriptions()->create([
                'name' => $this->name,
                'plan' => $this->plan->name(),
                'quantity' => $this->quantity,
                'tax_percentage' => $this->owner->taxPercentage() ?: 0,
                'trial_ends_at' => $this->trialExpires,
                'cycle_started_at' => $now,
                'cycle_ends_at' => $this->nextPaymentAt,
            ]);

            if($this->coupon) {
                $this->coupon->validateFor($subscription);
            }

            $subscription->scheduleNewOrderItemAt($this->nextPaymentAt);

            $subscription->save();

            if($this->coupon && $this->validateCoupon) {
                $this->coupon->redeemFor($subscription);
            }

            return $subscription;
        });
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
}
