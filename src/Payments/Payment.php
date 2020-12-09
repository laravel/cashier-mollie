<?php

namespace Laravel\Cashier\Payments;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var string[]|bool
     */
    protected $guarded = [];

    /**
     * @param array $models
     * @return \Laravel\Cashier\Payments\PaymentCollection
     */
    public function newCollection(array $models = []): PaymentCollection
    {
        return new PaymentCollection($models);
    }
}
