<?php

namespace Laravel\Cashier\SubscriptionBuilder\Contracts;

interface SubscriptionBuilder extends SubscriptionConfigurator
{
    /**
     * Create a new Cashier subscription. Returns a redirect to checkout if necessary.
     *
     * @return mixed
     */
    public function create();
}
