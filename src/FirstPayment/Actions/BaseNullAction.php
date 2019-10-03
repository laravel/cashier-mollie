<?php

namespace Laravel\Cashier\FirstPayment\Actions;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Order\OrderItemCollection;

abstract class BaseNullAction extends BaseAction
{
    /**
     * Rebuild the Action from a payload.
     *
     * @param array $payload
     * @param \Illuminate\Database\Eloquent\Model $owner
     */
    public static function createFromPayload(array $payload, Model $owner)
    {
        //
    }

    public function getPayload()
    {
        //
    }

    /**
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function makeProcessedOrderItems()
    {
        return new OrderItemCollection;
    }

    /**
     * Execute this action and return the created OrderItem or OrderItemCollection.
     *
     * @return \Laravel\Cashier\Order\OrderItem|\Laravel\Cashier\Order\OrderItemCollection
     */
    public function execute()
    {
        return new OrderItemCollection;
    }
}
