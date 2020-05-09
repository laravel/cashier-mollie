<?php

namespace Laravel\Cashier\OneOffPayment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Mollie\Api\Resources\Payment;

final class OneOffPaymentHandler
{
    /** @var \Illuminate\Database\Eloquent\Model */
    protected $owner;

    /** @var \Mollie\Api\Resources\Payment */
    protected $payment;

    /** @var \Laravel\Cashier\Order\OrderItemCollection */
    protected $items;

    /**
     * FirstPaymentHandler constructor.
     *
     * @param \Mollie\Api\Resources\Payment $payment
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
        $this->extractOwnerAndItems($payment);
    }

    /**
     * @return void
     */
    private function extractOwnerAndItems(Payment $payment)
    {
        // Extract owner
        $ownerType = $payment->metadata->owner->type;
        $ownerID = $payment->metadata->owner->id;

        $this->owner = Model::getActualClassNameForMorph($ownerType)::findOrFail($ownerID);

        // Extract items from payment
        $items = (array) $payment->metadata->items;
        $orderIds = Arr::pluck($items, 'id');

        $this->items = OrderItem::forOwner($this->owner)
            ->whereIn('id', $orderIds)
            ->get();
    }

    /**
     * Execute all actions for the mandate payment and return the created Order.
     *
     * @return \Laravel\Cashier\Order\Order
     */
    public function execute()
    {
        return Order::createProcessedFromItems($this->items, [
            'mollie_payment_id' => $this->payment->id,
            'mollie_payment_status' => $this->payment->status,
        ]);
    }

    /**
     * Retrieve the owner object.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Retrieve all Action objects.
     *
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function getItems()
    {
        return $this->items;
    }
}
