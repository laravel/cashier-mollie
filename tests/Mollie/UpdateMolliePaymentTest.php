<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Mollie;

use Illuminate\Support\Str;
use Laravel\Cashier\Mollie\Contracts\UpdateMolliePayment;
use Mollie\Api\Resources\Payment;

class UpdateMolliePaymentTest extends BaseMollieInteractionTest
{
    /**
     * @test
     * @group mollie_integration
     */
    public function testExecute()
    {
        /** @var UpdateMolliePayment $action */
        $action = $this->app->make(UpdateMolliePayment::class);
        $payment = mollie()->payments->get($this->getUpdatablePaymentId());
        $oldWebhookUrl = $payment->webhookUrl;
        $newWebhookUrl = 'https://example.com/' .Str::uuid();
        $payment->webhookUrl = $newWebhookUrl;

        $result = $action->execute($payment);

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertEquals($newWebhookUrl, $result->webhookUrl);
        $this->assertNotEquals($oldWebhookUrl, $result->webhookUrl);
    }

    protected function getUpdatablePaymentId(): string
    {
        return 'tr_BfTtmpEGVF';
    }
}
