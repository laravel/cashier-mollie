<?php

namespace Laravel\Cashier\Charge;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Charge\Contracts\ChargeBuilder as Contract;
use Laravel\Cashier\Order\Order;

class MandatedChargeBuilder implements Contract
{
    protected Model $owner;

    protected ChargeItemCollection $items;

    protected string $paymentDescription = '';

    protected Carbon $processAt;

    protected bool $processNow = true;

    public function __construct(Model $owner)
    {
        $this->owner = $owner;
        $this->items = new ChargeItemCollection;
        $this->processAt = Carbon::now();
    }

    public function setItems(ChargeItemCollection $items): self
    {
        $this->items = $items;

        return $this;
    }

    public function addItem(ChargeItem $item): self
    {
        $this->items->add($item);

        return $this;
    }

    public function paymentDescription(string $description): self
    {
        $this->paymentDescription = $description;

        return $this;
    }

    public function processAt(Carbon $pointInTime): self
    {
        $this->processAt = $pointInTime;
        $this->processNow = $pointInTime->isPast();

        return $this;
    }

    /**
     * @throws \Laravel\Cashier\Exceptions\InvalidMandateException
     * @return ?Order
     */
    public function create(): ?Order
    {
        $this->owner->guardMollieMandate();

        $items = $this->items->toOrderItemCollection([
            'process_at' => $this->processAt,
        ])->save();

        if ($this->processNow) {
            return Order::createFromItems($items)->processPayment();
        }

        return null;
    }
}
