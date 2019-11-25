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

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }
}
