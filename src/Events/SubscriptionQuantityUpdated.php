<?php

namespace Laravel\Cashier\Events;

use Illuminate\Queue\SerializesModels;
use Laravel\Cashier\Subscription;

class SubscriptionQuantityUpdated
{
    use SerializesModels;

    /**
     * @var \Laravel\Cashier\Subscription
     */
    public $subscription;

    /**
     * @var int
     */
    public $oldQuantity;

    public function __construct(Subscription $subscription, int $oldQuantity)
    {
        $this->subscription = $subscription;
        $this->oldQuantity = $oldQuantity;
    }
}
