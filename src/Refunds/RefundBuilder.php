<?php
declare(strict_types=1);

namespace Laravel\Cashier\Refunds;

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

    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->items = new RefundItemCollection;
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

        return $refund->addItems(RefundItemCollection::fromOrderItemCollection($order->items));
    }

    public function addItem(RefundItem $item): self
    {
        $this->items->add($item);

        return $this;
    }

    public function addItems(RefundItemCollection $items): self
    {
        $this->items->concat($items);

        return $this;
    }

    public function addItemFromOrderItem(OrderItem $orderItem): self
    {
        return $this->addItem(RefundItem::makeFromOrderItem($orderItem));
    }

    public function addItemFromOrderItemCollection(OrderItemCollection $orderItems): self
    {
        return $this->addItems(RefundItemCollection::fromOrderItemCollection($orderItems));
    }

    protected static function guardOrderIsPaid(Order $order)
    {
        throw_unless(
            $order->mollie_payment_status === PaymentStatus::STATUS_PAID,
            new LogicException('Only paid orders can be refunded')
        );
    }

    public function create()
    {
        // TODO create unprocessed Refund model
        // TODO create unprocessed RefundItem models
        // TODO initiate Mollie refund if applicable
        // TODO return Cashier Refund
    }
}
