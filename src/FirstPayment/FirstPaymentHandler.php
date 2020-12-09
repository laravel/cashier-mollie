<?php

namespace Laravel\Cashier\FirstPayment;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Events\MandateUpdated;
use Laravel\Cashier\FirstPayment\Actions\BaseAction;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItemCollection;
use Mollie\Api\Resources\Payment;

class FirstPaymentHandler
{
    /** @var \Illuminate\Database\Eloquent\Model */
    protected $owner;

    /** @var \Mollie\Api\Resources\Payment */
    protected $payment;

    /** @var \Illuminate\Support\Collection */
    protected $actions;

    /**
     * FirstPaymentHandler constructor.
     *
     * @param \Mollie\Api\Resources\Payment $payment
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
        $this->owner = $this->extractOwner();
        $this->actions = $this->extractActions();
    }

    /**
     * Execute all actions for the mandate payment and return the created Order.
     *
     * @return \Laravel\Cashier\Order\Order
     */
    public function execute()
    {
        $order = DB::transaction(function () {
            $this->owner->mollie_mandate_id = $this->payment->mandateId;
            $this->owner->save();

            $orderItems = $this->executeActions();

            return Order::createProcessedFromItems($orderItems, [
                'mollie_payment_id' => $this->payment->id,
                'mollie_payment_status' => $this->payment->status,
            ]);
        });

        event(new MandateUpdated($this->owner, $this->payment));

        return $order;
    }

    /**
     * Fetch the owner model using the mandate payment metadata.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function extractOwner()
    {
        $ownerType = $this->payment->metadata->owner->type;
        $ownerID = $this->payment->metadata->owner->id;

        return $ownerType::findOrFail($ownerID);
    }

    /**
     * Build the action objects from the payment metadata.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function extractActions()
    {
        $actions = new Collection((array) $this->payment->metadata->actions);

        return $actions->map(function ($actionMeta) {
            return $actionMeta->handler::createFromPayload(
                object_to_array_recursive($actionMeta),
                $this->owner
            );
        });
    }

    /**
     * Execute the Actions and return a collection of the resulting OrderItems.
     * These OrderItems are already paid for using the mandate payment.
     *
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    protected function executeActions()
    {
        $orderItems = new OrderItemCollection();

        $this->actions->each(function (BaseAction $action) use (&$orderItems) {
            $orderItems = $orderItems->concat($action->execute());
        });

        return $orderItems;
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
     * @return \Illuminate\Support\Collection
     */
    public function getActions()
    {
        return $this->actions;
    }
}
