<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Mollie;

use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment;
use Mollie\Api\Resources\Payment;

class CreateMolliePaymentTest extends BaseMollieInteractionTest
{
    /**
     * @test
     * @group mollie_integration
     */
    public function testExecute()
    {
        /** @var CreateMolliePayment $action */
        $action = $this->app->make(CreateMolliePayment::class);

        $result = $action->execute([
            'amount' => [
                'value' => '10.00',
                'currency' => 'EUR',
            ],
            'description' => 'Cashier integration test - CreateMolliePaymentTest',
            'redirectUrl' => 'https://www.sandervanhooft.com',
        ]);

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertEquals('10.00', $result->amount->value);
        $this->assertEquals('EUR', $result->amount->currency);
        $this->assertEquals('Cashier integration test - CreateMolliePaymentTest', $result->description);
        $this->assertEquals('https://www.sandervanhooft.com', $result->redirectUrl);
    }
}
