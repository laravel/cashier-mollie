<?php

namespace Laravel\Cashier\Tests\Order;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Laravel\Cashier\Order\Invoice;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Tests\BaseTestCase;

class InvoiceTest extends BaseTestCase
{
    /** @test */
    public function canAddItemsToInvoice()
    {
        $itemA = factory(OrderItem::class)->make([
            'unit_price' => 1000,
            'quantity' => 2,
            'tax_percentage' => 21.5,
            'currency' => 'EUR',
        ]);

        $itemB = factory(OrderItem::class)->make([
            'unit_price' => 1000,
            'quantity' => 2,
            'tax_percentage' => 21.5,
            'currency' => 'EUR',
        ]);

        $itemC = factory(OrderItem::class)->make([
            'unit_price' => 1000,
            'quantity' => 2,
            'tax_percentage' => 9.5,
            'currency' => 'EUR',
        ]);

        $invoice = new Invoice('EUR');

        $invoice
            ->addItems(new Collection([$itemA, $itemB]))
            ->addItem($itemC);

        $this->assertEquals('EUR', $invoice->currency());
        $this->assertMoneyEURCents(6000, $invoice->rawSubtotal());
        $this->assertEquals('60,00 €', $invoice->subtotal());

        $this->assertEquals(collect([
            [
                'tax_percentage' => 9.5,
                'raw_over_subtotal' => 2000,
                'over_subtotal' => '20,00 €',
                'raw_total' => 190,
                'total' => '1,90 €',
            ],
            [
                'tax_percentage' => 21.5,
                'raw_over_subtotal' => 4000,
                'over_subtotal' => '40,00 €',
                'raw_total' => 860,
                'total' => '8,60 €',
            ],
        ]), $invoice->taxDetails());

        $this->assertEquals('70,50 €', $invoice->total());
        $this->assertMoneyEURCents(7050, $invoice->rawTotal());
    }

    /** @test */
    public function canSetReceiverAddress()
    {
        $invoice = new Invoice('EUR');

        $invoice = $invoice->setReceiverAddress([
            'John Doe',
            'john@example.com',
        ]);

        $this->assertEquals(collect([
            'John Doe',
            'john@example.com',
        ]), $invoice->receiverAddress());

        $this->assertEquals('John Doe<br>john@example.com', $invoice->receiverAddress('<br>'));
    }

    /** @test */
    public function canSetDate()
    {
        $now = Carbon::parse('Feb 14 2016');
        Carbon::setTestNow($now);

        $invoice = new Invoice('EUR');
        $this->assertCarbon($now, $invoice->date());

        $invoice = new Invoice('EUR', null, Carbon::parse('Feb 15 2016'));
        $this->assertCarbon($now->addDay(), $invoice->date());

        $invoice = $invoice->setDate(Carbon::parse('Feb 15 2018'));
        $this->assertCarbon($now->addYears(2), $invoice->date());
    }

    /** @test */
    public function canAddExtraInformation()
    {
        $invoice = new Invoice('EUR');

        $invoice->setExtraInformation([
            'This is some nice extra information',
        ]);

        $this->assertEquals(collect(['This is some nice extra information']), $invoice->extraInformation());
        $this->assertEquals('This is some nice extra information', $invoice->extraInformation(''));
    }

    /** @test */
    public function canSetInvoiceId()
    {
        $invoice = new Invoice('EUR', '2018-1234567890');
        $this->assertEquals('2018-1234567890', $invoice->id());

        $invoice = $invoice->setId('2018-0987654321');
        $this->assertEquals('2018-0987654321', $invoice->id());
    }

    /** @test */
    public function canSetStartingBalance()
    {
        $invoice = new Invoice('EUR');
        $this->assertFalse($invoice->hasStartingBalance());
        $this->assertMoneyEURCents(0, $invoice->rawStartingBalance());
        $this->assertEquals('0,00 €', $invoice->startingBalance());

        $invoice = $invoice->setStartingBalance(money(1525, 'EUR'));
        $this->assertTrue($invoice->hasStartingBalance());
        $this->assertMoneyEURCents(1525, $invoice->rawStartingBalance());
        $this->assertEquals('15,25 €', $invoice->startingBalance());
    }

    /** @test */
    public function canSetCompletedBalance()
    {
        $invoice = new Invoice('EUR');
        $this->assertMoneyEURCents(0, $invoice->rawCompletedBalance());
        $this->assertEquals('0,00 €', $invoice->completedBalance());

        $invoice = $invoice->setCompletedBalance(money(1525, 'EUR'));
        $this->assertMoneyEURCents(1525, $invoice->rawCompletedBalance());
        $this->assertEquals('15,25 €', $invoice->completedBalance());
    }

    /** @test */
    public function canSetUsedBalance()
    {
        $invoice = new Invoice('EUR');
        $this->assertMoneyEURCents(0, $invoice->rawUsedBalance());
        $this->assertEquals('0,00 €', $invoice->usedBalance());

        $invoice = $invoice->setUsedBalance(money(1525, 'EUR'));
        $this->assertMoneyEURCents(1525, $invoice->rawUsedBalance());
        $this->assertEquals('15,25 €', $invoice->usedBalance());
    }

    /** @test */
    public function canGetAsView()
    {
        $items = factory(OrderItem::class, 2)->make();

        $invoice = new Invoice('EUR');
        $invoice = $invoice->addItems($items);

        $view = $invoice->view();
        $view->render();

        $viewData = (object) $view->getData();
        $this->assertEquals($viewData->invoice, $invoice);
        $item1 = $viewData->invoice->items()[0];
        $item2 = $viewData->invoice->items()[1];
        $this->assertTrue($item1->is($items[0]));
        $this->assertTrue($item2->is($items[1]));
    }

    /** @test */
    public function canGetAsPdf()
    {
        $items = factory(OrderItem::class, 2)->make();

        $invoice = new Invoice('EUR');
        $invoice = $invoice->addItems($items);

        $pdf = $invoice->pdf();

        $this->assertNotNull($pdf);
    }

    /** @test */
    public function canGetAsDownloadResponse()
    {
        Carbon::setTestNow(Carbon::parse('2018-12-31'));
        $items = factory(OrderItem::class, 2)->make();
        config(['app.name' => 'FooBar']);

        $invoice = new Invoice('EUR');
        $invoice = $invoice->addItems($items);
        $invoice->setId('TestNumber-123');

        $response = $invoice->download();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->contains('content-description', 'File Transfer'));
        $this->assertTrue($response->headers->contains('content-disposition', 'attachment; filename="TestNumber-123_foo_bar.pdf"'));
        $this->assertTrue($response->headers->contains('Content-Transfer-Encoding', 'binary'));
        $this->assertTrue($response->headers->contains('Content-Type', 'application/pdf'));
    }
}
