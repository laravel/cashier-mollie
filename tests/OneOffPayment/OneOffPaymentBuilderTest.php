<?php


namespace Laravel\Cashier\Tests\OneOffPayment;

use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment;
use Laravel\Cashier\Mollie\Contracts\GetMollieCustomer;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate;
use Laravel\Cashier\Mollie\Contracts\GetMollieMethodMinimumAmount;
use Laravel\Cashier\OneOffPayment\OneOffPaymentBuilder;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Tests\BaseTestCase;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Payment as MolliePayment;
use Mollie\Api\Types\SequenceType;

class OneOffPaymentBuilderTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withPackageMigrations();
    }

    /** @test */
    public function canBuildPayload()
    {
        $this->withMockedGetMollieCustomer();

        $owner = $this->getCustomerUser();

        $builder = new OneOffPaymentBuilder($owner, [
            'description' => 'Test mandate payment',
            'redirectUrl' => 'https://www.example.com',
            'webhookUrl' => 'https://www.example.com/mandate-webhook',
        ]);

        $builder->forItems(
            $items = factory(OrderItem::class, 2)
                ->states(['unlinked', 'unprocessed', 'EUR'])
                ->create([
                    'owner_type' => $owner->getMorphClass(),
                    'owner_id' => $owner->getKey(),
                    'unit_price' => 300,
                    'tax_percentage' => 10,
                ])
        );

        $payload = $builder->getMolliePayload();
        $customerId = $payload['customerId'];
        unset($payload['customerId']);
        $check_payload = [
            'sequenceType' => SequenceType::SEQUENCETYPE_ONEOFF,
            'description' => 'Test mandate payment',
            'amount' => [
                'value' => '6.60',
                'currency' => 'EUR',
            ],
            'redirectUrl' => 'https://www.example.com',
            'webhookUrl' => 'https://www.example.com/mandate-webhook',
            'metadata' => [
                'owner' => [
                    'type' => $owner->getMorphClass(),
                    'id' => $owner->getKey(),
                ],
            ],
        ];
        $this->assertEquals($check_payload, $payload);
        $this->assertNotEmpty($customerId);
    }

    /** @test */
    public function createsMolliePayment()
    {
        $this->withMockedGetMollieCustomer();
        $this->withMockedCreateMolliePayment();

        $owner = $this->getMandatedUser();

        $builder = new OneOffPaymentBuilder($owner, [
            'description' => 'Test mandate payment',
            'redirectUrl' => 'https://www.example.com',
        ]);

        $builder->forItems(
            $items = factory(OrderItem::class, 2)
                ->states(['unlinked', 'unprocessed', 'EUR'])
                ->create([
                    'owner_type' => $owner->getMorphClass(),
                    'owner_id' => $owner->getKey(),
                    'unit_price' => 300,
                    'tax_percentage' => 10,
                ])
        );

        $payment = $builder->create();

        $this->assertInstanceOf(Payment::class, $payment);
    }

    protected function withMockedGetMollieCustomer($times = 1)
    {
        $this->mock(GetMollieCustomer::class, function ($mock) use ($times) {
            $customer = new Customer(new MollieApiClient);
            $customer->id = 'cst_unique_customer_id';

            return $mock->shouldReceive('execute')
                ->with('cst_unique_customer_id')
                ->times($times)
                ->andReturn($customer);
        });
    }

    protected function withMockedGetMollieMandate($times = 1): void
    {
        $this->mock(GetMollieMandate::class, function ($mock) use ($times) {
            $mandate = new Mandate(new MollieApiClient);
            $mandate->id = 'mdt_unique_mandate_id';
            $mandate->status = 'valid';
            $mandate->method = 'directdebit';

            return $mock->shouldReceive('execute')
                ->with('cst_unique_customer_id', 'mdt_unique_mandate_id')
                ->times($times)
                ->andReturn($mandate);
        });
    }

    protected function withMockeGetMollieMethodMinimumAmount()
    {
        $this->mock(GetMollieMethodMinimumAmount::class, function ($mock) {
            return $mock->shouldReceive('execute')
                ->with('directdebit', 'EUR')
                ->once()
                ->andReturn(money(10, 'EUR'));
        });
    }

    protected function withMockedCreateMolliePayment(): void
    {
        $this->mock(CreateMolliePayment::class, function ($mock) {
            $payment = new MolliePayment(new MollieApiClient);
            $payment->id = 'tr_unique_payment_id';
            $payment->amount = (object) [
                'currency' => 'EUR',
                'value' => '30.00',
            ];
            $payment->mandateId = 'mdt_dummy_mandate_id';

            return $mock->shouldReceive('execute')
                ->once()
                ->andReturn($payment);
        });
    }
}
