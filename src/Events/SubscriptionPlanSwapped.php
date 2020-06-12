<?php

namespace Laravel\Cashier\Events;

use Illuminate\Queue\SerializesModels;
use Laravel\Cashier\Subscription;

class SubscriptionPlanSwapped
{
    use SerializesModels;

    /**
     * @var \Laravel\Cashier\Subscription
     */
    public $subscription;

    /**
     * The previous subscription plan before swapping if exists.
     *
     * @var mixed
     */
    public $previousPlan;

    public function __construct(Subscription $subscription, $previousPlan = null)
    {
        $this->subscription = $subscription;

        $this->previousPlan = $previousPlan;
    }
}
