<?php

namespace Laravel\Cashier\Tests\OneOffPayment;

use Laravel\Cashier\OneOffPayment\OneOffPaymentBuilder;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Mollie\Api\Resources\Payment;
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
        $owner = factory(User::class)->create();

        $builder = new OneOffPaymentBuilder($owner, [
            'description' => 'Test mandate payment',
            'redirectUrl' => 'https://www.example.com',
            'webhookUrl' => 'https://www.example.com/mandate-webhook'
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
                'items' => $items->toArray(),
            ],
        ];
        $this->assertEquals($check_payload, $payload);
        $this->assertNotEmpty($customerId);
    }

    /** @test */
    public function createsMolliePayment()
    {
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

    /** @test */
    public function parsesRedirectUrlPaymentIdUponPaymentCreation()
    {
        $owner = factory(User::class)->create();

        $builder = new OneOffPaymentBuilder($owner, [
            'redirectUrl' => 'https://www.example.com/{payment_id}',
        ]);

        $payment = $builder->forItems(
            factory(OrderItem::class, 1)->create()
        )->create();

        $this->assertStringContainsString($payment->id, $payment->redirectUrl);
    }
}
