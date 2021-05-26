<?php


namespace Laravel\Cashier\Tests\UpdatePaymentMethod;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\MandateUpdated;
use Laravel\Cashier\FirstPayment\Actions\AddGenericOrderItem;
use Laravel\Cashier\FirstPayment\FirstPaymentHandler;
use Laravel\Cashier\Payment as LocalPayment;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;

class UpdatePaymentMethodWithoutAddingToBalanceTest extends BaseTestCase
{
    /** @test */
    public function canUpdatePaymentMethodWithoutAddingToBalance()
    {
        $this->withPackageMigrations();

        $owner = factory(User::class)->create([
            'mollie_mandate_id' => 'mdt_unique_mandate_id',
        ]);

        $newPayment = $this->getNewMandatePaymentStub();
        LocalPayment::createFromMolliePayment($newPayment, $owner);

        $newHandler = new FirstPaymentHandler($newPayment);

        $this->assertTrue($owner->is($newHandler->getOwner()));

        $actions = $newHandler->getActions();

        $this->assertCount(1, $actions);

        $updatePaymentAction = $actions[0];
        $this->assertInstanceOf(AddGenericOrderItem::class, $updatePaymentAction);
        $this->assertMoneyEURCents(100, $updatePaymentAction->getTotal());
        $this->assertEquals('Payment method updated', $updatePaymentAction->getDescription());

        $this->assertNotNull($owner->mollie_mandate_id);

        Event::fake();
        $order = $newHandler->execute();

        $owner = $owner->fresh();

        $this->assertEquals(1, $order->items()->count());
        $this->assertEquals($newPayment->mandateId, $owner->mollie_mandate_id);

        Event::assertDispatched(MandateUpdated::class, function (MandateUpdated $e) use ($owner, $newPayment) {
            $this->assertTrue($e->owner->is($owner));
            $this->assertSame($e->payment->id, $newPayment->id);

            return true;
        });
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
                    'handler' => AddGenericOrderItem::class,
                    'description' => 'Payment method updated',
                    'quantity' => 1,
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
