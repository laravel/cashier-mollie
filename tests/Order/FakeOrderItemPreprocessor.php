<?php

namespace Laravel\Cashier\Tests\Order;

use Illuminate\Support\Arr;
use Laravel\Cashier\Order\BaseOrderItemPreprocessor;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Tests\BaseTestCase;

class FakeOrderItemPreprocessor extends BaseOrderItemPreprocessor {

    protected $items = [];
    protected $result;

    /**
     * @param \Laravel\Cashier\Order\OrderItemCollection $items
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function handle(OrderItemCollection $items)
    {
        $this->items[] = $items;

        return $this->result ?: $items;
    }

    public function withResult(OrderItemCollection $mockResult)
    {
        $this->result = $mockResult;

        return $this;
    }

    public function assertOrderItemHandled(OrderItem $item)
    {
        BaseTestCase::assertContains($item, Arr::flatten($this->items), "OrderItem `{$item->description}` was not handled");
    }
}
