<?php

namespace Laravel\Cashier\Tests\FirstPayment\Actions;

use Laravel\Cashier\FirstPayment\Actions\AddGenericOrderItem;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;

class AddGenericOrderItemTest extends BaseTestCase
{
    /** @test */
    public function canGetPayload()
    {
        $this->withPackageMigrations();

        $action = new AddGenericOrderItem(
            $this->getMandatedUser(true, ['tax_percentage' => 20]),
            money(5, 'EUR'),
            'Adding a test order item'
        );

        $payload = $action->getPayload();

        $this->assertEquals([
            'handler' => AddGenericOrderItem::class,
            'subtotal' => [
                'value' => '0.05',
                'currency' => 'EUR',
            ],
            'taxPercentage' => 20,
            'description' => 'Adding a test order item',
        ], $payload);
    }

    /** @test */
    public function canCreateFromPayload()
    {
        $action = AddGenericOrderItem::createFromPayload([
            'subtotal' => [
                'value' => '0.05',
                'currency' => 'EUR',
            ],
            'taxPercentage' => 20,
            'description' => 'Adding a test order item',
        ], factory(User::class)->make());

        $this->assertInstanceOf(AddGenericOrderItem::class, $action);
        $this->assertMoneyEURCents(5, $action->getSubtotal());
        $this->assertMoneyEURCents(6, $action->getTotal());
        $this->assertMoneyEURCents(1, $action->getTax());
        $this->assertEquals(20, $action->getTaxPercentage());
    }

    /** @test */
    public function canExecute()
    {
        $this->withPackageMigrations();
        $user = factory(User::class)->create(['tax_percentage' => 20]);
        $this->assertFalse($user->hasCredit());

        $action = new AddGenericOrderItem(
            $user,
            money(5, 'EUR'),
            'Adding a test order item'
        );

        $item = $action->execute();

        $this->assertInstanceOf(OrderItem::class, $item);
        $this->assertEquals('Adding a test order item', $item->description);
        $this->assertEquals('EUR', $item->currency);
        $this->assertEquals(5, $item->unit_price);
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals(20, $item->tax_percentage);
        $this->assertNotNull($item->id); // item is persisted
        $this->assertFalse($item->isProcessed());
    }
}
