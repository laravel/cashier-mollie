<?php

namespace Laravel\Cashier\Tests\SubscriptionBuilder;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\FirstPaymentPaid;
use Laravel\Cashier\Events\OrderProcessed;
use Laravel\Cashier\Events\SubscriptionStarted;
use Laravel\Cashier\Exceptions\CouponException;
use Laravel\Cashier\FirstPayment\Actions\AddGenericOrderItem;
use Laravel\Cashier\FirstPayment\Actions\StartSubscription;
use Laravel\Cashier\SubscriptionBuilder\FirstPaymentSubscriptionBuilder;
use Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse;
use Laravel\Cashier\Tests\BaseTestCase;
use Mollie\Api\Resources\Payment;

class FirstPaymentSubscriptionBuilderTest extends BaseTestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cashier::useCurrency('eur');
        $this->withTestNow('2019-01-01');
        $this->withPackageMigrations();
        $this->withConfiguredPlans();
        $this->user = $this->getCustomerUser(true, ['tax_percentage' => 20]);
    }

    /** @test */
    public function createsMandatePaymentForSubscription()
    {
        $firstPayment = config('cashier_plans.defaults.first_payment');
        $firstPayment["redirect_url"] = "https://foo-redirect-bar.com";
        $firstPayment["webhook_url"] = "https://foo-webhook-bar.com";
        config(["cashier_plans.plans.monthly-10-1.first_payment" => $firstPayment]);
        config(["cashier.locale" => "nl_NL"]);

        $builder = $this->getBuilder()
            ->nextPaymentAt(now()->addDays(12))
            ->trialDays(5);

        $response = $builder->create();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertInstanceOf(RedirectToCheckoutResponse::class, $response);
        $this->assertInstanceOf(Payment::class, $response->payment());

        $payload = $builder->getMandatePaymentBuilder()->getMolliePayload();

        $this->assertEquals([
            "sequenceType" => "first",
            "method" => "ideal",
            "customerId" => $this->user->mollie_customer_id,
            "description" => "Test mandate payment",
            "amount" => [
                "value" => "0.05",
                "currency" => "EUR",
            ],
            "webhookUrl" => "https://foo-webhook-bar.com",
            "redirectUrl" => "https://foo-redirect-bar.com",
            "locale" => "nl_NL",
            "metadata" => [
                "owner" => [
                    "type" => $this->user->getMorphClass(),
                    "id" => 1,
                ],
                "actions" => [
                    [
                        "handler" => StartSubscription::class,
                        "description" => "Monthly payment",
                        "subtotal" => [
                            "value" => "0.00",
                            "currency" => "EUR",
                        ],
                        "taxPercentage" => 20,
                        "plan" => "monthly-10-1",
                        "name" => "default",
                        "quantity" => 1,
                        "nextPaymentAt" => now()->addDays(12)->toIso8601String(),
                        "trialUntil" => now()->addDays(5)->toIso8601String(),
                    ],
                    [
                        "handler" => AddGenericOrderItem::class,
                        "description" => "Test mandate payment",
                        "subtotal" => [
                            "value" => "0.04",
                            "currency" => "EUR",
                        ],
                        "taxPercentage" => 20,
                    ],
                ],
            ],
        ], $payload);

        // For creating a new paid first payment, use:
        // dd(
        //     $response->payment()->getCheckoutUrl(), // visit this Mollie checkout url and set status to 'paid'
        //     $response->payment()->id // store this in phpunit.xml: SUBSCRIPTION_MANDATE_PAYMENT_PAID_ID
        // );
    }

    /** @test */
    public function handlesQuantity()
    {
        config(['cashier.locale' => 'nl_NL']);

        $builder = $this->getBuilder()->quantity(3);

        $response = $builder->create();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertInstanceOf(RedirectToCheckoutResponse::class, $response);
        $this->assertInstanceOf(Payment::class, $response->payment());

        $payload = $builder->getMandatePaymentBuilder()->getMolliePayload();

        $this->assertEquals(3, $payload['metadata']['actions'][0]['quantity']);
        $this->assertEquals([
            'currency' => 'EUR',
            'value' => 36,
        ], $payload['amount']);

    }

    /** @test */
    public function handlesAPaidMandatePayment()
    {
        $this->withoutExceptionHandling();

        Event::fake();

        $this->assertFalse($this->user->subscribed());
        $this->assertNull($this->user->mollie_mandate_id);

        $response = $this->post(route('webhooks.mollie.first_payment', [
            'id' => $this->getSubscriptionMandatePaymentID()
        ]));

        $response->assertStatus(200);

        $this->user = $this->user->fresh();
        $this->assertTrue($this->user->subscribed());
        $this->assertTrue($this->user->onTrial());
        $this->assertNotNull($this->user->mollie_mandate_id);

        Event::assertDispatched(OrderProcessed::class);
        Event::assertDispatched(FirstPaymentPaid::class);

        $subscription = $this->user->subscription('default')->fresh();

        Event::assertDispatched(SubscriptionStarted::class, function (SubscriptionStarted $e) use ($subscription) {
            $this->assertTrue($e->subscription->is($subscription));
            return true;
        });
    }

    /** @test */
    public function testWithCouponNoTrialValidatesCoupon()
    {
        $this->expectException(CouponException::class);
        $this->withMockedCouponRepository(null, new InvalidatingCouponHandler);
        $this->getBuilder()->withCoupon('test-coupon')->create();
    }

    /** @test */
    public function testWithCouponWithTrialValidatesCoupon()
    {
        $this->expectException(CouponException::class);
        $this->withMockedCouponRepository(null, new InvalidatingCouponHandler);
        $this->getBuilder()->trialDays(5)->withCoupon('test-coupon')->create();
    }

    /** @test */
    public function testWithCouponNoTrialModifiesThePaymentAmount()
    {
        $this->withMockedCouponRepository();
        $builder = $this->getBuilder()->withCoupon('test-coupon');
        $builder->create();

        $amount = $builder->getMandatePaymentBuilder()->getMolliePayload()['amount'];

        $this->assertEquals('7.00', $amount['value']);
        $this->assertEquals('EUR', $amount['currency']);
    }

    /** @test */
    public function testSkipTrialRevertsTheTrialAmount()
    {
        $trialBuilder = $this->getBuilder();

        $trialBuilder->trialDays(5)->create();
        $this->assertEquals(
            '0.05',
            $trialBuilder->getMandatePaymentBuilder()->getMolliePayload()['amount']['value']
        );

        $skipTrialBuilder = $this->getBuilder()->trialDays(5)->skipTrial();
        $skipTrialBuilder->create();
        $this->assertEquals(
            '12.00',
            $skipTrialBuilder->getMandatePaymentBuilder()->getMolliePayload()['amount']['value']
        );
    }

    /**
     * @return \Laravel\Cashier\SubscriptionBuilder\FirstPaymentSubscriptionBuilder
     */
    protected function getBuilder()
    {
        return new FirstPaymentSubscriptionBuilder(
            $this->user,
            'default',
            'monthly-10-1'
        );
    }
}
