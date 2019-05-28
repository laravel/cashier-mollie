<?php

namespace Laravel\Cashier\Types;

class SubscriptionCancellationReason
{
    /**
     * The reason for cancelling the subscription is unknown.
     */
    const UNKNOWN = "unknown";

    /**
     * The subscription is canceled because the payment has failed.
     */
    const PAYMENT_FAILED = "payment_failed";
}
