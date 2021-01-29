<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Mollie;

use Laravel\Cashier\Mollie\Contracts\CreateMollieRefund;
use Mollie\Api\Resources\Refund;

class CreateMollieRefundTest extends BaseMollieInteractionTest
{
    /**
     * @test
     * @group mollie_integration
     * @group requires_manual_intervention
     */
    public function testExecute()
    {
        // Manually create a new refundable payment first before running this.

        /** @var CreateMollieRefund $action */
        $action = $this->app->make(CreateMollieRefund::class);
        $paymentId = $this->getMollieRefundPaymentId();
        $result = $action->execute($paymentId, [
            'amount' => [
                'value' => '0.01',
                'currency' => 'EUR',
            ],
        ]);

        $this->assertInstanceOf(Refund::class, $result);
        $this->assertEquals($paymentId, $result->paymentId);
    }

    protected function getMollieRefundPaymentId()
    {
        return env('REFUND_PAYMENT_ID');
    }

    protected function getMollieRefundId()
    {
        return env('REFUND_ID');
    }
}
