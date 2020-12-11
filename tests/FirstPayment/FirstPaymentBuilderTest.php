<?php

namespace Laravel\Cashier\Tests\FirstPayment;

use Laravel\Cashier\FirstPayment\Actions\AddBalance;
use Laravel\Cashier\FirstPayment\Actions\AddGenericOrderItem;
use Laravel\Cashier\FirstPayment\FirstPaymentBuilder;
use Laravel\Cashier\Mollie\Contracts\CreateMollieCustomer;
use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\SequenceType;

class FirstPaymentBuilderTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withPackageMigrations();
        $customer = new Customer(new MollieApiClient);
        $customer->id = 'cst_unique_customer_id';

        $this->mock(CreateMollieCustomer::class, function ($mock) use ($customer) {
            return $mock->shouldReceive('execute')
                ->andReturn($customer);
        });
    }

    /** @test */
    public function canBuildPayload()
    {
        $owner = factory(User::class)->create();
        $this->assertEquals(0, $owner->orderItems()->count());
        $this->assertEquals(0, $owner->orders()->count());

        $builder = new FirstPaymentBuilder($owner, [
            'description' => 'Test mandate payment',
            'redirectUrl' => 'https://www.example.com',
        ]);

        $builder->inOrderTo([
            new AddBalance(
                $owner,
                money(500, 'EUR'),
                'Test add balance 1'
            ),
            new AddBalance(
                $owner,
                money(500, 'EUR'),
                'Test add balance 2'
            ),
        ]);

        $payload = $builder->getMolliePayload();
        $customerId = $payload['customerId'];
        unset($payload['customerId']);
        $check_payload = [
            'sequenceType' => SequenceType::SEQUENCETYPE_FIRST,
            'description' => 'Test mandate payment',
            'amount' => [
                'value' => '10.00',
                'currency' => 'EUR',
            ],
            'redirectUrl' => 'https://www.example.com',
            'webhookUrl' => 'https://www.example.com/mandate-webhook',
            'metadata' => [
                'owner' => [
                    'type' => get_class($owner),
                    'id' => $owner->id,
                ],
                'actions' => [
                    [
                        'handler' => AddBalance::class,
                        'subtotal' => [
                            'value' => '5.00',
                            'currency' => 'EUR',
                        ],
                        'taxPercentage' => 0,
                        'description' => 'Test add balance 1',
                    ],
                    [
                        'handler' => AddBalance::class,
                        'subtotal' => [
                            'value' => '5.00',
                            'currency' => 'EUR',
                        ],
                        'taxPercentage' => 0,
                        'description' => 'Test add balance 2',
                    ],
                ],
            ],
        ];

        $this->assertEquals($payload, $check_payload);
        $this->assertNotEmpty($customerId);
        $this->assertEquals(0, $owner->orderItems()->count());
        $this->assertEquals(0, $owner->orders()->count());
    }

    /** @test */
    public function createsMolliePayment()
    {
        $owner = factory(User::class)->create();
        $this->assertEquals(0, $owner->orderItems()->count());
        $this->assertEquals(0, $owner->orders()->count());

        $builder = new FirstPaymentBuilder($owner, [
            'description' => 'Test mandate payment',
            'redirectUrl' => 'https://www.example.com',
        ]);

        $builder->inOrderTo([
            new AddBalance(
                $owner,
                money(500, 'EUR'),
                'Test add balance 1'
            ),
            new AddBalance(
                $owner,
                money(500, 'EUR'),
                'Test add balance 2'
            ),
        ]);

        $this->mock(CreateMolliePayment::class, function (CreateMolliePayment $mock) {
            $payment = new Payment(new MollieApiClient);

            return $mock->shouldReceive('execute')
                ->once()
                ->andReturn($payment);
        });

        $payment = $builder->create();

        $this->assertEquals(0, $owner->orderItems()->count());
        $this->assertEquals(0, $owner->orders()->count());

        $this->assertInstanceOf(Payment::class, $payment);
    }

    /** @test */
    public function parsesRedirectUrlPaymentIdUponPaymentCreation()
    {
        $owner = factory(User::class)->create();

        $builder = new FirstPaymentBuilder($owner, [
            'redirectUrl' => 'https://www.example.com/{payment_id}',
        ]);

        $this->mock(CreateMolliePayment::class, function (CreateMolliePayment $mock) {
            $payment = new Payment(new MollieApiClient);
            $payment->redirectUrl = 'https://www.example.com/{payment_id}';
            $payment->id = 'tr_unique_id';

            return $mock->shouldReceive('execute')
                ->once()
                ->andReturn($payment);
        });

        $payment = $builder->inOrderTo([
            new AddGenericOrderItem($owner, money(100, 'EUR'), 'Parse redirectUrl test'),
        ])->create();

        $this->assertEquals('https://www.example.com/tr_unique_id', $payment->redirectUrl);
    }
}
