<?php

namespace Laravel\Cashier\Tests\OneOffPayments;

use Laravel\Cashier\Order\Invoice;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Tests\BaseTestCase;

class ManagesTabsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withPackageMigrations();
    }

    /** @test */
    public function canGetTheUpcomingInvoice()
    {
        $user = $this->getUser(true, ['tax_percentage' => 10]);
        $user->tab('A premium quality potato', 1000);
        $user->tab('A high quality carrot', 800);

        $inMemoryOrder = $user->upcomingOrderForTab(['number' => 'Concept']);

        $this->assertFalse($inMemoryOrder->exists);
        $this->assertFalse($inMemoryOrder->isProcessed());
        $this->assertSame('Concept', $inMemoryOrder->number);
        $this->assertMoneyEURCents(180, $inMemoryOrder->getTax());
        $this->assertMoneyEURCents(1800, $inMemoryOrder->getSubtotal());
        $this->assertMoneyEURCents(1980, $inMemoryOrder->getTotal());
        $this->assertInstanceOf(Invoice::class, $inMemoryOrder->invoice());

        $firstItem = $inMemoryOrder->items->first();
        $secondItem = $inMemoryOrder->items->last();
        $this->assertInstanceOf(OrderItem::class, $firstItem);
        $this->assertInstanceOf(OrderItem::class, $secondItem);
        $this->assertSame('A premium quality potato', $firstItem->description);
        $this->assertSame('A high quality carrot', $secondItem->description);
    }

    /** @test */
    public function returnFalseIfThereIsNoUpcomingInvoice()
    {
        $user = $this->getUser();

        $upcomingInvoice = $user->upcomingOrderForTab();

        $this->assertFalse($upcomingInvoice);
    }
}
