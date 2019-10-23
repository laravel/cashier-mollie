<?php

namespace Laravel\Cashier\Events;

use Laravel\Cashier\Subscription;

class SubscriptionStarted
{
    /**
     * @var \Laravel\Cashier\Subscription
     */
    public $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }
}
