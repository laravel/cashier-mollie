<?php

namespace Laravel\Cashier\Tests\Order;

use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Order\OrderItemPreprocessorCollection;
use Laravel\Cashier\Tests\BaseTestCase;

class OrderItemPreprocessorCollectionTest extends BaseTestCase
{
    /** @test */
    public function handlesOrderItem()
    {
        $fakePreprocessor = $this->getFakePreprocessor(factory(OrderItem::class, 2)->make());
        $preprocessors = new OrderItemPreprocessorCollection([$fakePreprocessor]);
        $item = factory(OrderItem::class)->make();

        $result = $preprocessors->handle($item);

        $this->assertInstanceOf(OrderItemCollection::class, $result);
        $this->assertEquals(2, $result->count());
        $fakePreprocessor->assertOrderItemHandled($item);
    }

    /** @test */
    public function invokesPreprocessorsOneByOne()
    {
        $preprocessor1 = $this->getFakePreprocessor(factory(OrderItem::class, 1)->make());
        $preprocessor2 = $this->getFakePreprocessor(factory(OrderItem::class, 2)->make());
        $preprocessors = new OrderItemPreprocessorCollection([$preprocessor1, $preprocessor2]);
        $item = factory(OrderItem::class)->make();

        $result = $preprocessors->handle($item);

        $this->assertInstanceOf(OrderItemCollection::class, $result);
        $this->assertEquals(2, $result->count());
    }

    /** @test */
    public function handlesEmptyPreprocessorCollection()
    {
        $preprocessors = new OrderItemPreprocessorCollection;
        $item = factory(OrderItem::class)->make();

        $result = $preprocessors->handle($item);

        $this->assertInstanceOf(OrderItemCollection::class, $result);
        $this->assertEquals(1, $result->count());
        $this->assertTrue($result->first()->is($item));
    }

    /**
     * @param \Laravel\Cashier\Order\OrderItemCollection $items
     * @return \Laravel\Cashier\Tests\Order\FakeOrderItemPreprocessor
     */
    protected function getFakePreprocessor(OrderItemCollection $items)
    {
        return (new FakeOrderItemPreprocessor)->withResult($items);
    }
}

