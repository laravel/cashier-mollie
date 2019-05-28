<?php

namespace Laravel\Cashier\Order\Contracts;

interface ProvidesInvoiceInformation
{
    /**
     * Get the receiver information for the invoice.
     * Typically includes the name and some sort of (E-mail/physical) address.
     *
     * @return array An array of strings
     */
    public function getInvoiceInformation();

    /**
     * Get additional information to be displayed on the invoice.
     * Typically a note provided by the customer.
     *
     * @return string|null
     */
    public function getExtraBillingInformation();
}
