<?php

namespace Laravel\Cashier\Tests;

use Laravel\Cashier\Payment;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment as MolliePayment;

class PaymentTest extends BaseTestCase
{
    /** @test */
    public function canCreateFromBasicMolliePayment()
    {
        $this->withPackageMigrations();

        $molliePayment = new MolliePayment(new MollieApiClient);
        $molliePayment->id = 'tr_dummy_payment_id';
        $molliePayment->status = 'dummy_status';
        $molliePayment->amount = (object) [
            'currency' => 'EUR',
            'value' => '12.34',
        ];
        $molliePayment->amountRefunded = null;
        $molliePayment->amountChargedBack = null;
        $user = $this->getMandatedUser();

        $localPayment = Payment::createFromMolliePayment($molliePayment, $user);

        $this->assertEquals('tr_dummy_payment_id', $localPayment->mollie_payment_id);
        $this->assertEquals('dummy_status', $localPayment->status);
        $this->assertEquals('EUR', $localPayment->currency);
        $this->assertMoneyEURCents(1234, $localPayment->getAmount());
        $this->assertMoneyEURCents(0, $localPayment->getAmountRefunded());
        $this->assertMoneyEURCents(0, $localPayment->getAmountChargedBack());
        $this->assertTrue($localPayment->owner->is($user));
    }
}
