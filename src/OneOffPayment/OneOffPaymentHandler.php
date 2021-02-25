<?php

namespace Laravel\Cashier\OneOffPayment;

use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Payment as LocalPayment;
use Mollie\Api\Resources\Payment as MolliePayment;

class OneOffPaymentHandler
{
    /** @var \Illuminate\Database\Eloquent\Model */
    protected $owner;

    /** @var \Mollie\Api\Resources\Payment */
    protected $molliePayment;

    /** @var \Laravel\Cashier\Order\OrderItemCollection */
    protected $items;

    /**
     * FirstPaymentHandler constructor.
     *
     * @param \Mollie\Api\Resources\Payment $molliePayment
     */
    public function __construct(MolliePayment $molliePayment)
    {
        $this->molliePayment = $molliePayment;
        $this->owner = $this->extractOwner();
        $this->items = $this->getItemsToBeProcessed();
    }

    /**
     * Execute all actions for the mandate payment and return the created Order.
     *
     * @return \Laravel\Cashier\Order\Order
     */
    public function execute()
    {
        $order = DB::transaction(function () {
            $order = Order::createProcessedFromItems($this->items, [
                'mollie_payment_id' => $this->molliePayment->id,
                'mollie_payment_status' => $this->molliePayment->status,
            ]);

            $payment = LocalPayment::findByPaymentIdOrFail($this->molliePayment->id);
            $payment->update([
                'order_id' => $order->id,
                'mollie_payment_status' => $this->molliePayment->status,
                'mollie_mandate_id' => $this->molliePayment->mandateId,
            ]);

            return $order;
        });

        return $order;
    }

    /**
     * Fetch the owner model using the mandate payment metadata.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function extractOwner()
    {
        $ownerType = $this->molliePayment->metadata->owner->type;
        $ownerID = $this->molliePayment->metadata->owner->id;

        return $ownerType::findOrFail($ownerID);
    }

    /**
     * Build the action objects from the payment metadata.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getItemsToBeProcessed()
    {
        return $this->items = OrderItem::shouldProcess()
            ->forOwner($this->owner)
            ->get();
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
}
