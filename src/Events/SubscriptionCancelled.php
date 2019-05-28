<?php

namespace Laravel\Cashier\Events;

use Illuminate\Queue\SerializesModels;
use Laravel\Cashier\Subscription;

class SubscriptionCancelled
{
    use SerializesModels;

    /**
     * The canceled subscription.
     *
     * @var Subscription
     */
    public $subscription;

    /**
     * Reason for the subscription being canceled.
     *
     * @var string
     */
    public $reason;

    /**
     * Creates a new SubscriptionCancelled event.
     *
     * @param Subscription $subscription
     * @param string $reason
     */
    public function __construct($subscription, $reason)
    {
        $this->subscription = $subscription;
        $this->reason = $reason;
    }
}
