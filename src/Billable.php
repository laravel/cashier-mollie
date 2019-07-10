<?php

namespace Laravel\Cashier;

use Laravel\Cashier\Credit\Credit;
use Laravel\Cashier\Events\MandateClearedFromBillable;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Plan\Contracts\PlanRepository;
use Laravel\Cashier\SubscriptionBuilder\FirstPaymentSubscriptionBuilder;
use Laravel\Cashier\SubscriptionBuilder\MandatedSubscriptionBuilder;
use Laravel\Cashier\Traits\PopulatesMollieCustomerFields;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Types\MandateMethod;
use Money\Money;

trait Billable
{
    use PopulatesMollieCustomerFields;

    /**
     * Get all of the subscriptions for the billable model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->morphMany(Subscription::class, 'owner');
    }

    /**
     * Get a subscription instance by name for the billable model.
     *
     * @param  string  $subscription
     * @return \Laravel\Cashier\Subscription|null
     */
    public function subscription($subscription = 'default')
    {
        return $this->subscriptions->sortByDesc(function ($value) {
            return $value->created_at->getTimestamp();
        })
        ->first(function ($value) use ($subscription) {
            return $value->name === $subscription;
        });
    }

    /**
     * Begin creating a new subscription. If necessary, the customer will be redirected to Mollie's checkout
     * to perform a first mandate payment.
     *
     * @param string $subscription
     * @param string $plan
     * @return \Laravel\Cashier\SubscriptionBuilder\Contracts\SubscriptionBuilder
     * @throws \Laravel\Cashier\Exceptions\PlanNotFoundException
     * @throws \Throwable
     */
    public function newSubscription($subscription, $plan)
    {
        if(! empty($this->mollie_mandate_id)) {

            $mandate = null;

            try {
                $mandate = $this->asMollieCustomer()->getMandate($this->mollie_mandate_id);
            } catch (ApiException $e) {} // A revoked mandate may no longer exist, so throws an exception

            $planModel = app(PlanRepository::class)::findOrFail($plan);
            $method = MandateMethod::getForFirstPaymentMethod($planModel->firstPaymentMethod());

            if(
                ! empty($mandate)
                && $mandate->isValid()
                && $mandate->method === $method
            ) {
                return $this->newSubscriptionForMandateId($this->mollie_mandate_id, $subscription, $plan);
            }
        }

        return $this->newSubscriptionViaMollieCheckout($subscription, $plan);
    }

    /**
     * Begin creating a new subscription. The customer will always be redirected to Mollie's checkout to make the first
     * mandate payment.
     *
     * @param $subscription
     * @param $plan
     * @return \Laravel\Cashier\SubscriptionBuilder\FirstPaymentSubscriptionBuilder
     * @throws \Laravel\Cashier\Exceptions\PlanNotFoundException
     */
    public function newSubscriptionViaMollieCheckout($subscription, $plan)
    {
        return new FirstPaymentSubscriptionBuilder($this, $subscription, $plan);
    }

    /**
     * Begin creating a new subscription for an already mandated customer.
     *
     * @param string $mandateId
     * @param  string $subscription
     * @param  string $plan
     * @return \Laravel\Cashier\SubscriptionBuilder\MandatedSubscriptionBuilder
     * @throws \Laravel\Cashier\Exceptions\PlanNotFoundException
     * @throws \Throwable
     */
    public function newSubscriptionForMandateId($mandateId, $subscription, $plan)
    {
        return new MandatedSubscriptionBuilder(
            $this,
            $subscription,
            $plan
        );
    }

    /**
     * Retrieve the Mollie customer ID for this model
     *
     * @return string
     */
    public function mollieCustomerId()
    {
        if(empty($this->mollie_customer_id)) {
            return $this->createAsMollieCustomer()->id;
        }

        return $this->mollie_customer_id;
    }

    /**
     * Create a Mollie customer for the billable model.
     *
     * @param array $override_options
     * @return Customer
     */
    public function createAsMollieCustomer(array $override_options = [])
    {
        $options = array_merge($this->mollieCustomerFields(), $override_options);

        $customer = mollie()->customers()->create($options);

        $this->mollie_customer_id = $customer->id;
        $this->save();

        return $customer;
    }

    /**
     * Fetch the Mollie Customer for the billable model.
     *
     * @return Customer
     */
    public function asMollieCustomer()
    {
        if(empty($this->mollie_customer_id)) {
            return $this->createAsMollieCustomer();
        }
        return mollie()->customers()->get($this->mollie_customer_id);
    }

    /**
     * Determine if the billable model is on trial.
     *
     * @param  string  $subscription
     * @param  string|null  $plan
     * @return bool
     */
    public function onTrial($subscription = 'default', $plan = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($subscription);

        if (is_null($plan)) {
            return $subscription && $subscription->onTrial();
        }

        return $subscription && $subscription->onTrial() &&
            $subscription->plan === $plan;
    }

    /**
     * Determine if the billable model is on a "generic" trial.
     *
     * @return bool
     */
    public function onGenericTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Cancel the generic trial if active.
     *
     * @return $this
     */
    public function cancelGenericTrial()
    {
        if($this->onGenericTrial()) {
            $this->forceFill(['trial_ends_at' => now()])->save();
        }

        return $this;
    }

    /**
     * Determine if the billable model has a given subscription.
     *
     * @param  string  $subscription
     * @param  string|null  $plan
     * @return bool
     */
    public function subscribed($subscription = 'default', $plan = null)
    {
        $subscription = $this->subscription($subscription);
        if (is_null($subscription)) {
            return false;
        }
        if (is_null($plan)) {
            return $subscription->valid();
        }
        return $subscription->valid() &&
               $subscription->plan === $plan;
    }

    /**
     * @param $plans
     * @param string $subscription
     * @return bool
     */
    public function subscribedToPlan($plans, $subscription = 'default')
    {
        $subscription = $this->subscription($subscription);
        if (! $subscription || ! $subscription->valid()) {
            return false;
        }
        foreach ((array) $plans as $plan) {
            if ($subscription->plan === $plan) {
                return true;
            }
        }
        return false;
    }

    public function hasActiveSubscriptionWithCurrency($currency)
    {
        return $this->subscriptions->contains(function ($subscription) use ($currency) {
            return $subscription->active() && $subscription->currency === $currency;
        });
    }

    /**
     * Get all of the Orders for the billable model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function orders()
    {
        return $this->morphMany(Order::class, 'owner');
    }

    /**
     * Get all of the Order Items for the billable model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function orderItems()
    {
        return $this->morphMany(OrderItem::class, 'owner');
    }

    /**
     * Get the balances for the billable model. A separate balance is kept for each currency.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function credits()
    {
        return $this->morphMany(Credit::class, 'owner');
    }

    /**
     * Checks whether the billable model has a credit balance.
     *
     * @param string|null $currency
     * @return bool
     */
    public function hasCredit($currency = null)
    {
        if(empty($currency)) {
            return $this->credits()
                ->where('value', '<>', 0)
                ->exists();
        }

        return $this->credits()
            ->whereCurrency($currency)
            ->where('value', '<>', 0)
            ->exists();
    }

    /**
     * Retrieve the credit balance for the billable model for a specific currency.
     *
     * @param $currency
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function credit($currency)
    {
        $credit = $this->credits()->whereCurrency($currency)->first();

        if(! $credit ) {
            $credit = $this->credits()->create([
                'currency' => $currency,
                'value' => 0,
            ]);
        }

        return $credit;
    }

    /**
     * Add a credit amount for the billable model balance.
     *
     * @param \Money\Money $amount
     * @return $this
     */
    public function addCredit(Money $amount)
    {
        Credit::addAmountForOwner($this, $amount);

        return $this;
    }

    /**
     * Use this model's max amount of credit.
     *
     * @param \Money\Money $amount
     * @return Money
     */
    public function maxOutCredit(Money $amount)
    {
        return Credit::maxOutForOwner($this, $amount);
    }

    /**
     * Get the tax percentage to apply to the subscription.
     * @example 20 (for 20%)
     *
     * @return float
     */
    public function taxPercentage()
    {
        return $this->tax_percentage ?? 0;
    }

    /**
     * Get the invoice instances for this model.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function invoices()
    {
        return $this->orders->invoices();
    }

    /**
     * @return null|string
     */
    public function mollieMandateId()
    {
        return $this->mollie_mandate_id;
    }

    /**
     * @return \Mollie\Api\Resources\Mandate|null
     */
    public function mollieMandate()
    {
        $id = $this->mollieMandateId();

        if(! empty($id)) {
            return $this->asMollieCustomer()->getMandate($id);
        }

        return null;
    }

    /**
     * @return bool
     */
    public function validMollieMandate()
    {
        $mandate = $this->mollieMandate();

        if(is_null($mandate)) {
            return false;
        }

        return $mandate->isValid();
    }

    /**
     * Checks whether the Mollie mandate is still valid. If not, clears it.
     *
     * @return bool
     */
    public function validateMollieMandate()
    {
        if($this->validMollieMandate()) {
            return true;
        }

        $this->clearMollieMandate();

        return false;
    }

    /**
     * @return \Laravel\Cashier\Billable
     */
    public function clearMollieMandate()
    {
        if(empty($this->mollieMandateId())) {
            return ;
        }

        $previousId = $this->mollieMandateId();

        $this->fill(['mollie_mandate_id' => null]);
        $this->save();

        dispatch(new MandateClearedFromBillable($this, $previousId));

        return $this;
    }
}
