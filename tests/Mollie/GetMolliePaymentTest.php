<?php
declare(strict_types=1);

namespace Laravel\Cashier\Tests\Mollie;

use Laravel\Cashier\Mollie\Contracts\GetMolliePayment;
use Laravel\Cashier\Tests\BaseTestCase;
use Mollie\Api\Resources\Payment;

class GetMolliePaymentTest extends BaseTestCase
{
    /**
     * @test
     * @group integration
     */
    public function testExecute()
    {
        /** @var GetMolliePayment $action */
        $action = $this->app->make(GetMolliePayment::class);
        $id = $this->getMandatePaymentID();
        $result = $action->execute($id);

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertEquals($id, $result->id);
    }
}
