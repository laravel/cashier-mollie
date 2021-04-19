<?php

namespace Laravel\Cashier\Tests\OneOffPayments;

use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ManagesInvoicesTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withPackageMigrations();
    }

    /** @test */
    public function canFindInvoices()
    {
        $user = $this->getCustomerUser();
        $items = factory(OrderItem::class, 2)
            ->states(['EUR'])
            ->create([
                'owner_id' => $user->getKey(),
                'owner_type' => $user->getMorphClass(),
                'unit_price' => 12150,
                'quantity' => 1,
                'tax_percentage' => 21.5,
                'orderable_type' => null,
                'orderable_id' => null,
            ]);
        $order = Order::createFromItems($items);

        $createdInvoice = $order->fresh()->invoice();
        $foundInvoice = $user->findInvoice($order->id);

        $this->assertEquals($createdInvoice, $foundInvoice);
        $this->assertNull($user->findInvoice('non-existing-order'));
    }

    /** @test */
    public function findInvoiceOrFailthrowIfEmpty()
    {
        $owner = $this->getCustomerUser();

        $items = factory(OrderItem::class, 2)
            ->states(['unlinked', 'EUR'])
            ->create([
                'owner_id' => $owner->id,
                'owner_type' => User::class,
                'unit_price' => 12150,
                'quantity' => 1,
                'tax_percentage' => 21.5,
            ]);
        $order = Order::createFromItems($items, [
            'balance_before' => 500,
            'credit_used' => 500,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        $createdInvoice = $order->invoice();
        $this->expectException(NotFoundHttpException::class);
        $foundInvoice = $owner->findInvoiceOrFail(2);
    }

    /** @test */
    public function canDownloadInvoice()
    {
        $owner = $this->getCustomerUser();
        $items = factory(OrderItem::class, 2)
            ->states(['unlinked', 'EUR'])
            ->create([
                'owner_id' => $owner->id,
                'owner_type' => User::class,
                'unit_price' => 12150,
                'quantity' => 1,
                'tax_percentage' => 21.5,
            ]);
        $order = Order::createFromItems($items, [
            'balance_before' => 500,
            'credit_used' => 500,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        $createdInvoice = $order->invoice();

        $response = $owner->downloadInvoice(1);

        $this->assertTrue($response->headers->get('content-description') == 'File Transfer');
        $this->assertTrue($response->headers->get('content-type') == 'application/pdf');
    }

    /** @test */
    public function returnFalseOnInvoiceWithoutItems()
    {
        $owner = $this->getCustomerUser();

        $itemsToOrder = factory(OrderItem::class, 2)
            ->states(['unlinked', 'EUR'])
            ->create([
                'owner_id' => $owner->id,
                'owner_type' => User::class,
                'unit_price' => 12150,
                'quantity' => 1,
                'tax_percentage' => 21.5,
                'process_at' => now()->subMonth(),
            ]);
        $order = Order::createFromItems($itemsToOrder, [
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        $createdInvoice = $owner->invoiceTab();

        $this->assertFalse($createdInvoice);
    }
}
