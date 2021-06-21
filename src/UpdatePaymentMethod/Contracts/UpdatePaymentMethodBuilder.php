<?php

namespace Laravel\Cashier\UpdatePaymentMethod\Contracts;

interface UpdatePaymentMethodBuilder
{
    /**
     * Update payment method.
     *
     * @return \Laravel\Cashier\Http\RedirectToCheckoutResponse
     */
    public function create();
}
