<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Mollie;

use Laravel\Cashier\Mollie\Contracts\GetMollieRefund;
use Mollie\Api\Resources\Refund;

class GetMollieRefundTest extends BaseMollieInteractionTest
{
    /**
     * @test
     * @group mollie_integration
     */
    public function testExecute()
    {
        /** @var GetMollieRefund $action */
        $action = $this->app->make(GetMollieRefund::class);
        $paymentId = $this->getMollieRefundPaymentId();
        $refundId = $this->getMollieRefundId();
        $result = $action->execute($paymentId, $refundId);

        $this->assertInstanceOf(Refund::class, $result);
        $this->assertEquals($refundId, $result->id);
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
