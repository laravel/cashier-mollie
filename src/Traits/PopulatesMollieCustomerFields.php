<?php

namespace Laravel\Cashier\Traits;

trait PopulatesMollieCustomerFields
{
    /**
     * Returns the customer fields used when creating a Mollie customer
     * See: https://docs.mollie.com/reference/v2/customers-api/create-customer for the available fields
     *
     * @return array
     */
    public function mollieCustomerFields()
    {
        return [
            'email' => $this->email,
            'name' => $this->name,
            //'locale' => $this->locale,
            //'metadata' => [
            //    'id' => $this->id,
            //],
        ];
    }
}
