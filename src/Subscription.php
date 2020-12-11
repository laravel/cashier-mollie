<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Coupon\AppliedCoupon;
use Laravel\Cashier\Coupon\Contracts\AcceptsCoupons;
use Laravel\Cashier\Coupon\RedeemedCoupon;
use Laravel\Cashier\Events\SubscriptionCancelled;
use Laravel\Cashier\Events\SubscriptionPlanSwapped;
use Laravel\Cashier\Events\SubscriptionQuantityUpdated;
use Laravel\Cashier\Events\SubscriptionResumed;
use Laravel\Cashier\Events\SubscriptionStarted;
use Laravel\Cashier\Order\Contracts\InteractsWithOrderItems;
use Laravel\Cashier\Order\Contracts\PreprocessesOrderItems;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Plan\Contracts\Plan;
use Laravel\Cashier\Plan\Contracts\PlanRepository;
use Laravel\Cashier\Traits\HasOwner;
use Laravel\Cashier\Types\SubscriptionCancellationReason;
use LogicException;
use Money\Money;

/**
 * @property int id
 * @property \Carbon\Carbon cycle_ends_at
 * @property \Carbon\Carbon cycle_started_at
 * @property \Carbon\Carbon ends_at
 * @property mixed owner_id
 * @property string owner_type
 * @property string next_plan
 * @property string plan
 * @property int quantity
 * @property mixed scheduled_order_item_id
 * @property OrderItem scheduledOrderItem
 * @property float tax_percentage
 * @property \Carbon\Carbon trial_ends_at
 * @property float cycle_progress
 * @property float cycle_left
 */
class Subscription extends Model implements InteractsWithOrderItems, PreprocessesOrderItems, AcceptsCoupons
{
    use HasOwner;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'trial_ends_at',
        'cycle_started_at',
        'cycle_ends_at',
        'ends_at',
    ];

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => SubscriptionStarted::class,
    ];

    /**
     * Determine if the subscription is valid.
     *
     * @return bool
     */
    public function valid()
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active()
    {
        return is_null($this->ends_at) || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     *
     * @return bool
     */
    public function ended()
    {
        return $this->cancelled() && ! $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Determine if the subscription is recurring and not on trial.
     *
     * @return bool
     */
    public function recurring()
    {
        return ! $this->onTrial() && ! $this->cancelled();
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Helper function to determine the current billing cycle progress ratio.
     * Ranging from 0 (not started) to 1 (completed).
     *
     * @param  Carbon|null $now
     * @param int $precision
     * @return float
     */
    public function getCycleProgressAttribute($now = null, $precision = 5)
    {
        $now = $now ?: now();
        $cycle_started_at = $this->cycle_started_at->copy();
        $cycle_ends_at = $this->cancelled() ? $this->ends_at->copy() : $this->cycle_ends_at->copy();

        // Cycle completed
        if ($cycle_ends_at->lessThanOrEqualTo($now)) {
            return 1;
        }

        // Cycle not yet started
        if ($cycle_started_at->greaterThanOrEqualTo($now)) {
            return 0;
        }

        $total_cycle_seconds = $cycle_started_at->diffInSeconds($cycle_ends_at);
        $seconds_progressed = $cycle_started_at->diffInSeconds($now);

        return round($seconds_progressed / $total_cycle_seconds, $precision);
    }

    /**
     * Helper function to determine the current billing cycle inverted progress ratio.
     * Ranging from 0 (completed) to 1 (not yet started).
     *
     * @param \Carbon\Carbon|null $now
     * @param int|null $precision
     * @return float
     */
    public function getCycleLeftAttribute(?Carbon $now = null, ?int $precision = 5)
    {
        return (float) 1 - $this->getCycleProgressAttribute($now, $precision);
    }

    /**
     * Swap the subscription to another plan right now by ending the current billing cycle and starting a new one.
     * A new Order is processed along with the payment.
     *
     * @param string $plan
     * @param bool $invoiceNow
     * @return $this
     */
    public function swap(string $plan, $invoiceNow = true)
    {
        /** @var Plan $newPlan */
        $newPlan = app(PlanRepository::class)::findOrFail($plan);
        $previousPlan = $this->plan;

        if ($this->cancelled()) {
            $this->cycle_ends_at = $this->ends_at;
            $this->ends_at = null;
        }

        $applyNewSettings = function () use ($newPlan) {
            $this->plan = $newPlan->name();
        };

        $this->restartCycleWithModifications($applyNewSettings, now(), $invoiceNow);

        Event::dispatch(new SubscriptionPlanSwapped($this, $previousPlan));

        return $this;
    }

    /**
     * Swap the subscription to a new plan, and invoice immediately.
     *
     * @param string $plan
     * @return $this
     */
    public function swapAndInvoice($plan)
    {
        return $this->swap($plan, true);
    }

    /**
     * Schedule this subscription to be swapped to another plan once the current cycle has completed.
     *
     * @param string $plan
     * @return $this
     */
    public function swapNextCycle(string $plan)
    {
        $new_plan = app(PlanRepository::class)::findOrFail($plan);

        return DB::transaction(function () use ($plan, $new_plan) {
            $this->next_plan = $plan;

            $this->removeScheduledOrderItem();

            $this->scheduleNewOrderItemAt($this->cycle_ends_at, [], true, $new_plan);

            $this->save();

            return $this;
        });
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @param string|null $reason
     * @return $this
     */
    public function cancel($reason = SubscriptionCancellationReason::UNKNOWN)
    {
        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll retrieve the end of the billing cycle
        // period and make that the end of the grace period for this current user.

        $grace_ends_at = $this->onTrial() ? $this->trial_ends_at : $this->cycle_ends_at;

        return $this->cancelAt($grace_ends_at, $reason);
    }

    /**
     * Cancel the subscription at the date provided.
     *
     * @param \Carbon\Carbon $endsAt
     * @param string $reason
     * @return $this
     */
    public function cancelAt(Carbon $endsAt, $reason = SubscriptionCancellationReason::UNKNOWN)
    {
        return DB::transaction(function () use ($reason, $endsAt) {
            $this->removeScheduledOrderItem();

            $this->fill([
                'ends_at' => $endsAt,
                'cycle_ends_at' => null,
            ])->save();

            Event::dispatch(new SubscriptionCancelled($this, $reason));

            return $this;
        });
    }

    /**
     * Cancel the subscription immediately.
     *
     * @param string $reason
     * @return $this
     */
    public function cancelNow($reason = SubscriptionCancellationReason::UNKNOWN)
    {
        return $this->cancelAt(now(), $reason);
    }

    /**
     * Remove the subscription's scheduled order item.
     * Optionally persists the reference removal on the subscription.
     *
     * @param false bool $save
     * @return $this
     * @throws \Exception
     */
    protected function removeScheduledOrderItem($save = false)
    {
        $item = $this->scheduledOrderItem;

        if ($item && $item->isProcessed(false)) {
            $item->delete();
        }

        $this->fill(['scheduled_order_item_id' => null]);

        if ($save) {
            $this->save();
        }

        return $this;
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return $this
     *
     * @throws \LogicException
     */
    public function resume()
    {
        if (! $this->cancelled()) {
            throw new LogicException('Unable to resume a subscription that is not cancelled.');
        }

        if (! $this->onGracePeriod()) {
            throw new LogicException('Unable to resume a subscription that is not within grace period.');
        }

        return DB::transaction(function () {
            $item = $this->scheduleNewOrderItemAt($this->ends_at);

            $this->fill([
                'cycle_ends_at' => $this->ends_at,
                'ends_at' => null,
                'scheduled_order_item_id' => $item->id,
            ])->save();

            Event::dispatch(new SubscriptionResumed($this));

            return $this;
        });
    }

    /**
     * Get the order items for this subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function orderItems()
    {
        return $this->morphMany(OrderItem::class, 'orderable');
    }

    /**
     * Relation to the scheduled order item, if defined.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function scheduledOrderItem()
    {
        return $this->hasOne(OrderItem::class, 'id', 'scheduled_order_item_id');
    }

    /**
     * Relation to the scheduled order item, if defined.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function scheduled_order_item()
    {
        return $this->scheduledOrderItem();
    }

    /**
     * Schedule a new subscription order item at the provided datetime.
     *
     * @param Carbon|null $process_at
     * @param array $item_overrides
     * @param bool $fill_link Indicates whether scheduled_order_item_id field should be filled to point to the newly scheduled order item
     *
     * @param \Laravel\Cashier\Plan\Contracts\Plan $plan
     * @return \Illuminate\Database\Eloquent\Model|\Laravel\Cashier\Order\OrderItem
     */
    public function scheduleNewOrderItemAt(Carbon $process_at, $item_overrides = [], $fill_link = true, Plan $plan = null)
    {
        if ($this->scheduled_order_item_id) {
            throw new LogicException('Cannot schedule a new subscription order item if there is already one scheduled.');
        }

        if (is_null($plan)) {
            $plan = $this->plan();
        }

        $item = $this->orderItems()->create(array_merge(
            [
                'owner_id' => $this->owner_id,
                'owner_type' => $this->owner_type,
                'process_at' => $process_at,
                'currency' => $plan->amount()->getCurrency()->getCode(),
                'unit_price' => (int) $plan->amount()->getAmount(),
                'quantity' => $this->quantity ?: 1,
                'tax_percentage' => $this->tax_percentage,
                'description' => $plan->description(),
            ],
            $item_overrides
        ));

        if ($fill_link) {
            $this->fill([
                'scheduled_order_item_id' => $item->id,
            ]);
        }

        return $item;
    }

    /**
     * Called right before processing the order item into an order.
     *
     * @param OrderItem $item
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public static function preprocessOrderItem(OrderItem $item)
    {
        /** @var Subscription $subscription */
        $subscription = $item->orderable;

        return $subscription->plan()->orderItemPreprocessors()->handle($item);
    }

    /**
     * Called after processing the order item into an order.
     *
     * @param OrderItem $item
     * @return OrderItem The order item that's being processed
     */
    public static function processOrderItem(OrderItem $item)
    {
        /** @var Subscription scheduled_order_item_id */
        $subscription = $item->orderable;
        $plan_swapped = false;
        $previousPlan = null;

        if (! empty($subscription->next_plan)) {
            $plan_swapped = true;
            $previousPlan = $subscription->plan;
            $subscription->plan = $subscription->next_plan;
            $subscription->next_plan = null;
        }

        $item = DB::transaction(function () use (&$subscription, $item) {
            $next_cycle_ends_at = $subscription->cycle_ends_at->copy()->modify('+' . $subscription->plan()->interval());
            $subscription->cycle_started_at = $subscription->cycle_ends_at;
            $subscription->cycle_ends_at = $next_cycle_ends_at;

            // Requires cleared scheduled order item before continuing
            $subscription->scheduled_order_item_id = null;

            $subscription->scheduleNewOrderItemAt($subscription->cycle_ends_at);
            $subscription->save();

            $item->description_extra_lines = [
                'From ' . $subscription->cycle_started_at->format('Y-m-d') . ' to ' . $subscription->cycle_ends_at->format('Y-m-d'),
            ];

            return $item;
        });

        if ($plan_swapped) {
            Event::dispatch(new SubscriptionPlanSwapped($subscription, $previousPlan));
        }

        return $item;
    }

    /**
     * Sync the tax percentage of the owner to the subscription.
     *
     * @return Subscription
     */
    public function syncTaxPercentage()
    {
        return DB::transaction(function () {
            $this->update([
                'tax_percentage' => $this->owner->taxPercentage(),
            ]);

            return $this;
        });
    }

    /**
     * Get the plan instance for this subscription.
     *
     * @return \Laravel\Cashier\Plan\Plan
     */
    public function plan()
    {
        return app(PlanRepository::class)::find($this->plan);
    }

    /**
     * Get the plan instance for this subscription's next cycle.
     *
     * @return \Laravel\Cashier\Plan\Plan
     */
    public function nextPlan()
    {
        return app(PlanRepository::class)::find($this->next_plan);
    }

    /**
     * Get the currency for this subscription.
     * @example EUR
     *
     * @return string
     */
    public function getCurrencyAttribute()
    {
        return optional($this->plan())->amount()->getCurrency()->getCode();
    }

    /**
     * Handle a failed payment.
     *
     * @param \Laravel\Cashier\Order\OrderItem $item
     * @return void
     */
    public static function handlePaymentFailed(OrderItem $item)
    {
        $subscription = $item->orderable;

        $endsAt = $subscription->onTrial() ? $subscription->trial_ends_at : now();

        $subscription->cancelAt($endsAt, SubscriptionCancellationReason::PAYMENT_FAILED);
    }

    /**
     * Handle a paid payment.
     *
     * @param \Laravel\Cashier\Order\OrderItem $item
     * @return void
     */
    public static function handlePaymentPaid(OrderItem $item)
    {
        // Subscriptions are prolonged optimistically (so before payment is being completely processed).
    }

    /**
     * Increment the quantity of the subscription.
     *
     * @param int $count
     * @param bool $invoiceNow
     * @return \Laravel\Cashier\Subscription
     * @throws \Throwable
     */
    public function incrementQuantity(int $count = 1, $invoiceNow = true)
    {
        return $this->updateQuantity($this->quantity + $count, $invoiceNow);
    }

    /**
     * Increment the quantity of the subscription, and invoice immediately.
     *
     * @param int $count
     * @return \Laravel\Cashier\Subscription
     * @throws \Throwable
     */
    public function incrementAndInvoice($count = 1)
    {
        return $this->incrementQuantity($count, true);
    }

    /**
     * Decrement the quantity of the subscription.
     *
     * @param int $count
     * @param bool $invoiceNow
     * @return \Laravel\Cashier\Subscription
     * @throws \Throwable
     */
    public function decrementQuantity(int $count = 1, $invoiceNow = true)
    {
        return $this->updateQuantity($this->quantity - $count, $invoiceNow);
    }

    /**
     * Update the quantity of the subscription.
     *
     * @param int $quantity
     * @param bool $invoiceNow
     * @return $this
     * @throws \Throwable
     */
    public function updateQuantity(int $quantity, $invoiceNow = true)
    {
        throw_if(
            $quantity < 1,
            new LogicException('Subscription quantity must be at least 1.')
        );

        $oldQuantity = $this->quantity;

        $this->restartCycleWithModifications(function () use ($quantity) {
            $this->quantity = $quantity;
        }, now(), $invoiceNow);

        $this->save();

        Event::dispatch(new SubscriptionQuantityUpdated($this, $oldQuantity));

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->trial_ends_at = null;

        return $this;
    }

    /**
     * @param \Money\Money $amount
     * @param array $overrides
     * @return OrderItem
     */
    protected function reimburse(Money $amount, array $overrides = [])
    {
        return $this->owner->orderItems()->create(array_merge([
            'process_at' => now(),
            'description' => $this->plan()->description(),
            'currency' => $amount->getCurrency()->getCode(),
            'unit_price' => $amount->getAmount(),
            'quantity' => $this->quantity ?: 1,
            'tax_percentage' => $this->tax_percentage,
        ], $overrides));
    }

    /**
     * @param \Carbon\Carbon|null $now
     * @return null|\Laravel\Cashier\Order\OrderItem
     */
    protected function reimburseUnusedTime(?Carbon $now = null)
    {
        $now = $now ?: now();
        if ($this->onTrial()) {
            return null;
        }

        $plan = $this->plan();
        $amount = $plan->amount()->negative()->multiply($this->getCycleLeftAttribute($now));

        return $this->reimburse($amount, [ 'description' => $plan->description() ]);
    }

    /**
     * Wrap up the current billing cycle, apply modifications to this subscription and start a new cycle.
     *
     * @param \Closure $applyNewSettings
     * @param \Carbon\Carbon|null $now
     * @param bool $invoiceNow
     * @return \Laravel\Cashier\Subscription
     */
    public function restartCycleWithModifications(\Closure $applyNewSettings, ?Carbon $now = null, $invoiceNow = true)
    {
        $now = $now ?: now();

        return DB::transaction(function () use ($applyNewSettings, $now, $invoiceNow) {

            // Wrap up current billing cycle
            $this->removeScheduledOrderItem();
            $reimbursement = $this->reimburseUnusedTime($now);

            $orderItems = (new OrderItemCollection([$reimbursement]))->filter();

            // Apply new subscription settings
            call_user_func($applyNewSettings);

            if ($this->onTrial()) {

                // Reschedule next cycle's OrderItem using the new subscription settings
                $orderItems[] = $this->scheduleNewOrderItemAt($this->trial_ends_at);
            } else { // Start a new billing cycle using the new subscription settings

                // Reset the billing cycle
                $this->cycle_started_at = $now;
                $this->cycle_ends_at = $now;

                // Create a new OrderItem, starting a new billing cycle
                $orderItems[] = $this->scheduleNewOrderItemAt($now);
            }

            $this->save();

            if ($invoiceNow) {
                $order = Order::createFromItems($orderItems);
                $order->processPayment();
            }

            return $this;
        });
    }

    /**
     * Wrap up the current billing cycle and start a new cycle.
     *
     * @param \Carbon\Carbon|null $now
     * @param bool $invoiceNow
     * @return \Laravel\Cashier\Subscription
     */
    public function restartCycle(?Carbon $now = null, $invoiceNow = true)
    {
        return $this->restartCycleWithModifications(function () {
        }, $now, $invoiceNow);
    }

    /**
     * Any coupons redeemed for this subscription
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function redeemedCoupons()
    {
        return $this->morphMany(RedeemedCoupon::class, 'model');
    }

    /**
     * Any coupons applied to this subscription
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function appliedCoupons()
    {
        return $this->morphMany(AppliedCoupon::class, 'model');
    }

    /**
     * @return string
     */
    public function ownerType()
    {
        return $this->owner_type;
    }

    /**
     * @return mixed
     */
    public function ownerId()
    {
        return $this->owner_id;
    }
}
