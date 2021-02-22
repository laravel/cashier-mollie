<?php

namespace Laravel\Cashier\UpdatePaymentMethod\Contracts;

interface UpdatePaymentMethodBuilder
{
    /**
     * Update payment method. Returns a redirect to checkout.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create();
}
