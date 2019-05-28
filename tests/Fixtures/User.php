<?php

namespace Laravel\Cashier\Tests\Fixtures;

use Laravel\Cashier\Billable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Order\Contracts\ProvidesInvoiceInformation;

class User extends Model implements ProvidesInvoiceInformation
{
    use Billable;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Get the receiver information for the invoice.
     * Typically includes the name and some sort of (E-mail/physical) address.
     *
     * @return array An array of strings
     */
    public function getInvoiceInformation()
    {
        return [$this->name, $this->email];
    }

    /**
     * Get additional information to be displayed on the invoice.
     * Typically a note provided by the customer.
     *
     * @return string|null
     */
    public function getExtraBillingInformation()
    {
        return $this->extra_billing_information;
    }
}
