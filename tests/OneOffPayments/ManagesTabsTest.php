<?php

namespace Laravel\Cashier\Tests\OneOffPayments;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\OrderCreated;
use Laravel\Cashier\Events\OrderProcessed;
use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment;
use Laravel\Cashier\Mollie\Contracts\GetMollieCustomer;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate;
use Laravel\Cashier\Mollie\Contracts\GetMollieMethodMinimumAmount;
use Laravel\Cashier\Order\Invoice;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;
use Mollie\Api\Resources\Payment as MolliePayment;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
