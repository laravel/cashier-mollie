<?php


namespace Laravel\Cashier\Tests\UpdatePaymentMethod;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\MandateUpdated;
use Laravel\Cashier\FirstPayment\Actions\AddBalance;
use Laravel\Cashier\FirstPayment\FirstPaymentHandler;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;

class UpdatePaymentMethodBuilderTest extends BaseTestCase
{
    /** @test */
    public function canUpdatePaymentMethodBuild()
    {
        $this->withPackageMigrations();
        Event::fake();

        $payment = $this->getMandatePaymentStub();

        $owner = factory(User::class)->create([
            'id' => $payment->metadata->owner->id,
            'mollie_customer_id' => 'cst_unique_customer_id',
        ]);

        $handler = new FirstPaymentHandler($payment);

        $this->assertTrue($owner->is($handler->getOwner()));

        $actions = $handler->getActions();
        $this->assertCount(1, $actions);

        $firstAction = $actions[0];
        $this->assertInstanceOf(AddBalance::class, $firstAction);
        $this->assertMoneyEURCents(1000, $firstAction->getTotal());
        $this->assertEquals('Test add balance 1', $firstAction->getDescription());

        $this->assertFalse($owner->hasCredit());
        $this->assertNull($owner->mollie_mandate_id);

        $this->assertEquals(0, $owner->orderItems()->count());
        $this->assertEquals(0, $owner->orders()->count());

        $order = $handler->execute();

        $owner = $owner->fresh();

        $this->assertTrue($owner->hasCredit());
        $credit = $owner->credit('EUR');
        $this->assertMoneyEURCents(1000, $credit->money());

        $this->assertEquals(1, $owner->orderItems()->count());
        $this->assertEquals(1, $owner->orders()->count());

        $this->assertInstanceOf(Order::class, $order);
        $this->assertTrue($order->isProcessed());

        $this->assertEquals(1, $order->items()->count());

        $this->assertNotNull($owner->mollie_mandate_id);
        $this->assertEquals($payment->mandateId, $owner->mollie_mandate_id);

        Event::assertDispatched(MandateUpdated::class, function (MandateUpdated $e) use ($owner, $payment) {
            $this->assertTrue($e->owner->is($owner));
            $this->assertSame($e->payment->id, $payment->id);

            return true;
        });

        $newPayment = $this->getNewMandatePaymentStub();

        $newHandler = new FirstPaymentHandler($newPayment);

        $this->assertTrue($owner->is($newHandler->getOwner()));

        $actions = $newHandler->getActions();
        $this->assertCount(1, $actions);

        $updatePaymentAction = $actions[0];
        $this->assertInstanceOf(AddBalance::class, $updatePaymentAction);
        $this->assertMoneyEURCents(100, $updatePaymentAction->getTotal());
        $this->assertEquals('Payment method updated', $updatePaymentAction->getDescription());

        $this->assertNotNull($owner->mollie_mandate_id);

        Event::fake();
        $order = $newHandler->execute();

        $owner = $owner->fresh();

        $this->assertTrue($owner->hasCredit());
        $credit = $owner->credit('EUR');
        $this->assertMoneyEURCents(1100, $credit->money());

        $this->assertEquals(2, $owner->orderItems()->count());
        $this->assertEquals(2, $owner->orders()->count());

        $this->assertInstanceOf(Order::class, $order);
        $this->assertTrue($order->isProcessed());

        $this->assertEquals(1, $order->items()->count());
        $this->assertEquals($newPayment->mandateId, $owner->mollie_mandate_id);
        $owner = $owner->fresh();

        Event::assertDispatched(MandateUpdated::class, function (MandateUpdated $e) use ($owner, $newPayment) {
            $this->assertTrue($e->owner->is($owner));
            $this->assertSame($e->payment->id, $newPayment->id);

            return true;
        });
    }

    protected function getMandatePaymentStub(): Payment
    {
        $payment = new Payment(new MollieApiClient());
        $payment->sequenceType = 'first';
        $payment->id = 'tr_unique_mandate_payment_id';
        $payment->customerId = 'cst_unique_customer_id';
        $payment->mandateId = 'mdt_unique_mandate_id';
        $payment->amount = (object) ['value' => '10.00', 'currency' => 'EUR'];
        $payment->metadata = json_decode(json_encode([
            'owner' => [
                'type' => User::class,
                'id' => 1,
            ],
            'actions' => [
                [
                    'handler' => AddBalance::class,
                    'description' => 'Test add balance 1',
                    'subtotal' => [
                        'currency' => 'EUR',
                        'value' => '10.00',
                    ],
                    'taxPercentage' => 0,
                ],
            ],
        ]));

        return $payment;
    }

    protected function getNewMandatePaymentStub(): Payment
    {
        $newPayment = new Payment(new MollieApiClient());
        $newPayment->sequenceType = 'first';
        $newPayment->id = 'new_tr_unique_mandate_payment_id';
        $newPayment->customerId = 'cst_unique_customer_id';
        $newPayment->mandateId = 'new_mdt_unique_mandate_id';
        $newPayment->amount = (object) ['value' => '1.00', 'currency' => 'EUR'];
        $newPayment->metadata = json_decode(json_encode([
            'owner' => [
                'type' => User::class,
                'id' => 1,
            ],
            'actions' => [
                [
                    'handler' => AddBalance::class,
                    'description' => 'Payment method updated',
                    'subtotal' => [
                        'currency' => 'EUR',
                        'value' => '1.00',
                    ],
                    'taxPercentage' => 0,
                ],
            ],
        ]));

        return $newPayment;
    }
}
