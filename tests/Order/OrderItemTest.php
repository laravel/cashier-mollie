<?php

namespace Laravel\Cashier\Tests\Order;

use Illuminate\Database\Eloquent\Relations\Relation;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;

class OrderItemTest extends BaseTestCase
{
    public function testGetSubtotalAttribute()
    {
        $item = new OrderItem;
        $item->currency = 'EUR';
        $item->quantity = 4;
        $item->unit_price = 110;
        $item->tax_percentage = 21.50; // should be excluded from calculation

        // 440 = (4 * 110 - 200)
        $this->assertEquals(440, $item->getSubtotalAttribute());
        $this->assertEquals(440, $item->subtotal);
        $this->assertMoneyEURCents(440, $item->getSubtotal());
    }

    public function testGetTaxAttribute()
    {
        $item = new OrderItem;
        $item->currency = 'EUR';
        $item->quantity = 4;
        $item->unit_price = 110;
        $item->tax_percentage = 21.5;

        // 94.6 = (4 * 110) * (21.5 / 100)
        $this->assertEquals(95, $item->getTaxAttribute());
        $this->assertEquals(95, $item->tax);
        $this->assertMoneyEURCents(95, $item->getTax());
    }

    public function testGetTotalAttribute()
    {
        $item = new OrderItem;
        $item->currency = 'EUR';
        $item->quantity = 4;
        $item->unit_price = 110;
        $item->tax_percentage = 21.5;

        // 534.6 = 4 * 110 + (4 * 110) * (21.5 / 100)
        $this->assertEquals(535, $item->getTotalAttribute());
        $this->assertEquals(535, $item->total);
        $this->assertMoneyEURCents(535, $item->getTotal());
    }

    public function testGetAttributesAsMoney()
    {
        $item = new OrderItem;
        $item->currency = 'EUR';
        $item->quantity = 4;
        $item->unit_price = 110;
        $item->tax_percentage = 21.5;

        $this->assertMoneyEURCents(535, $item->getTotal());
        $this->assertMoneyEURCents(110, $item->getUnitPrice());
        $this->assertMoneyEURCents(95, $item->getTax());
    }

    public function testScopeProcessed()
    {
        $this->withPackageMigrations();

        factory(OrderItem::class, 3)->create([
            'order_id' => null,
        ]);
        factory(OrderItem::class, 2)->create([
            'order_id' => 1,
        ]);

        $this->assertEquals(2, OrderItem::processed()->count());
        $this->assertEquals(2, OrderItem::processed(true)->count());
        $this->assertEquals(3, OrderItem::processed(false)->count());
    }

    public function testOrderItemTableHasCorrectOrderableType()
    {
        $newUserMorphKey = 'user';
        $newSubscriptionMorphKey = 'subscription';

        Relation::morphMap([
            $newUserMorphKey => User::class,
            $newSubscriptionMorphKey => Subscription::class,
        ]);

        $this->withPackageMigrations();

        $orderItem = factory(OrderItem::class)->create();

        $this->assertEquals($newUserMorphKey, (new User())->getMorphClass());
        $this->assertEquals($newSubscriptionMorphKey, (new Subscription())->getMorphClass());
        $this->assertEquals((new User())->getMorphClass(), $orderItem->owner_type);
        $this->assertEquals((new Subscription())->getMorphClass(), $orderItem->orderable_type);
    }

    public function testScopeUnprocessed()
    {
        $this->withPackageMigrations();

        factory(OrderItem::class, 3)->create([
            'order_id' => null,
        ]);
        factory(OrderItem::class, 2)->create([
            'order_id' => 1,
        ]);

        $this->assertEquals(3, OrderItem::unprocessed()->count());
        $this->assertEquals(3, OrderItem::unprocessed(true)->count());
        $this->assertEquals(2, OrderItem::unprocessed(false)->count());
    }

    public function testScopeShouldProcess()
    {
        $this->withPackageMigrations();

        factory(OrderItem::class, 2)->create([
            'order_id' => 1,
            'process_at' => now()->subHour(),
        ]);
        factory(OrderItem::class, 2)->create([
            'order_id' => null,
            'process_at' => now()->addDay(),
        ]);
        factory(OrderItem::class, 3)->create([
            'order_id' => null,
            'process_at' => now()->subHour(),
        ]);

        $this->assertEquals(3, OrderItem::shouldProcess()->count());
    }

    public function testScopeDue()
    {
        $this->withPackageMigrations();

        factory(OrderItem::class, 2)->create([
            'process_at' => now()->subHour(),
        ]);
        factory(OrderItem::class, 3)->create([
            'process_at' => now()->addMinutes(5),
        ]);

        $this->assertEquals(2, OrderItem::due()->count());
    }

    public function testNewCollection()
    {
        $collection = factory(OrderItem::class, 2)->make();
        $this->assertInstanceOf(OrderItemCollection::class, $collection);
    }

    public function testIsProcessed()
    {
        $unprocessedItem = factory(OrderItem::class)->make(['order_id' => null]);
        $processedItem = factory(OrderItem::class)->make(['order_id' => 1]);

        $this->assertFalse($unprocessedItem->isProcessed());
        $this->assertTrue($unprocessedItem->isProcessed(false));

        $this->assertTrue($processedItem->isProcessed());
        $this->assertFalse($processedItem->isProcessed(false));
    }
}
