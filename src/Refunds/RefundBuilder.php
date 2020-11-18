<?php
declare(strict_types=1);

namespace Laravel\Cashier\Refunds;

use Laravel\Cashier\Events\RefundInitiated;
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

    public function __construct(Order $order)
    {
        $this->order = $order;
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
        $total = money_to_decimal($this->items->getTotal());
        $currency = $this->order->getCurrency();

        $mollieRefund = $this->createMollieRefund->execute($this->order->mollie_payment_id, [
            'amount' => [
                'value' => money_to_decimal($this->items->getTotal()),
                'currency' => $currency,
            ],
        ]);

        $refundRecord = Refund::create([
            'owner_type' => $this->order->owner_type,
            'owner_id' => $this->order->owner_id,
            'original_order_id' => $this->order->getKey(),
            'total' => $total,
            'currency' => $currency,
            'mollie_refund_id' => $mollieRefund->id,
            'mollie_refund_status' => $mollieRefund->status,
        ]);

        $refundRecord->items()->saveMany($this->items);

        event(new RefundInitiated($refundRecord));

        return $refundRecord;
    }
}
