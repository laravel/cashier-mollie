<?php
declare(strict_types=1);

namespace Laravel\Cashier\Refunds;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Mollie\Contracts\CreateMollieRefund;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use LogicException;
use Mollie\Api\Types\PaymentStatus;

class RefundBuilder
{
    /**
     * @var \Laravel\Cashier\Order\Order
     */
    protected Order $order;

    /**
     * @var \Laravel\Cashier\Refunds\RefundItemCollection
     */
    protected RefundItemCollection $items;

    /**
     * @var CreateMollieRefund
     */
    protected CreateMollieRefund $createMollieRefund;

    /**
     * @var Model
     */
    private Model $owner;

    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->owner = $this->order->owner;
        $this->items = new RefundItemCollection;
        $this->createMollieRefund = app()->make(CreateMollieRefund::class);
    }

    public static function forOrder(Order $order): self
    {
        static::guardOrderIsPaid($order);

        return new static($order);
    }

    public static function forWholeOrder(Order $order): self
    {
        static::guardOrderIsPaid($order);
        $refund = new static($order);

        return $refund->addItems(RefundItemCollection::makeFromOrderItemCollection($order->items));
    }

    public function addItem(RefundItem $item): self
    {
        $this->items->add($item);

        return $this;
    }

    public function addItems(RefundItemCollection $items): self
    {
        $this->items = $this->items->concat($items);

        return $this;
    }

    public function addItemFromOrderItem(OrderItem $orderItem, array $overrides = []): self
    {
        return $this->addItem(RefundItem::makeFromOrderItem($orderItem, $overrides));
    }

    public function addItemsFromOrderItemCollection(OrderItemCollection $orderItems, array $overrides = []): self
    {
        return $this->addItems(RefundItemCollection::makeFromOrderItemCollection($orderItems, $overrides));
    }

    protected static function guardOrderIsPaid(Order $order)
    {
        throw_unless(
            $order->mollie_payment_status === PaymentStatus::STATUS_PAID,
            new LogicException('Only paid orders can be refunded')
        );
    }

    public function create(): Refund
    {
        $mollieRefund = $this->createMollieRefund->execute($this->order->mollie_payment_id, [
            'amount' => [
                'value' => money_to_decimal($this->order->getTotalDue()), // TODO use $this->items->total()
                'currency' => $this->order->getCurrency(),
            ]
        ]);

        $refund = Refund::create([
            'original_order_id' => $this->order->getKey(),
            'owner_type' => $this->owner->getMorphClass(),
            'owner_id' => $this->owner->getKey(),
            'mollie_refund_id' => $mollieRefund->id,
        ]);

        $refund->items()->saveMany($this->items);

        return $refund;
    }
}
