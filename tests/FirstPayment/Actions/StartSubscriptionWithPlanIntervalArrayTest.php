<?php

namespace Laravel\Cashier\Tests\FirstPayment\Actions;

use Carbon\Carbon;
use Laravel\Cashier\FirstPayment\Actions\StartSubscription;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate;
use Laravel\Cashier\Mollie\GetMollieCustomer;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Tests\BaseTestCase;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;

class StartSubscriptionWithPlanIntervalArrayTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withPackageMigrations()
             ->withConfiguredPlansWithIntervalArray()
             ->withTestNow('2019-01-29');
    }

    /** @test
     */
    public function canGetBasicPayloadPlanIntervalArray()
    {
        $action = new StartSubscription(
            $this->getMandatedUser(true, ['tax_percentage' => 20]),
            'default',
            'monthly-10-1'
        );

        $payload = $action->getPayload();

        $this->assertEquals([
            'handler' => StartSubscription::class,
            'description' => 'Monthly payment',
            'subtotal' => [
                'value' => '10.00',
                'currency' => 'EUR',
            ],
            'taxPercentage' => 20,
            'plan' => 'monthly-10-1',
            'name' => 'default',
            'quantity' => 1,
        ], $payload);
    }

    /** @test */
    public function canGetPayloadWithTrialDaysPlanIntervalArray()
    {
        $action = new StartSubscription(
            $this->getMandatedUser(true, ['tax_percentage' => 20]),
            'default',
            'monthly-10-1'
        );

        $action->trialDays(5);

        $payload = $action->getPayload();

        $this->assertEquals([
            'handler' => StartSubscription::class,
            'description' => 'Monthly payment',
            'subtotal' => [
                'value' => '0.00',
                'currency' => 'EUR',
            ],
            'taxPercentage' => 20,
            'plan' => 'monthly-10-1',
            'name' => 'default',
            'quantity' => 1,
            'trialDays' => 5,
        ], $payload);
    }

    /** @test */
    public function canGetPayloadWithTrialUntilPlanIntervalArray()
    {
        $action = new StartSubscription(
            $this->getMandatedUser(true, ['tax_percentage' => 20]),
            'default',
            'monthly-10-1'
        );

        $action->trialUntil(now()->addDays(5));

        $payload = $action->getPayload();

        $this->assertEquals([
            'handler' => StartSubscription::class,
            'description' => 'Monthly payment',
            'subtotal' => [
                'value' => '0.00',
                'currency' => 'EUR',
            ],
            'taxPercentage' => 20,
            'plan' => 'monthly-10-1',
            'name' => 'default',
            'quantity' => 1,
            'trialUntil' => '2019-02-03T00:00:00+00:00',
        ], $payload);
    }

    /** @test */
    public function canGetPayloadWithSkipTrialPlanIntervalArray()
    {
        $action = new StartSubscription(
            $this->getMandatedUser(true, ['tax_percentage' => 20]),
            'default',
            'monthly-10-1'
        );

        $action->trialUntil(now()->addDays(5))->skipTrial();

        $payload = $action->getPayload();

        $this->assertEquals([
            'handler' => StartSubscription::class,
            'description' => 'Monthly payment',
            'subtotal' => [
                'value' => '10.00',
                'currency' => 'EUR',
            ],
            'taxPercentage' => 20,
            'plan' => 'monthly-10-1',
            'name' => 'default',
            'quantity' => 1,
            'skipTrial' => true,
        ], $payload);
    }

    /** @test */
    public function canGetPayloadWithCouponPlanIntervalArray()
    {
        $this->withMockedCouponRepository();

        $action = new StartSubscription(
            $this->getMandatedUser(true, ['tax_percentage' => 20]),
            'default',
            'monthly-10-1'
        );

        $action->withCoupon('test-coupon');

        $payload = $action->getPayload();

        $this->assertEquals([
            'handler' => StartSubscription::class,
            'description' => 'Monthly payment',
            'subtotal' => [
                'value' => '10.00',
                'currency' => 'EUR',
            ],
            'taxPercentage' => 20,
            'plan' => 'monthly-10-1',
            'name' => 'default',
            'quantity' => 1,
            'coupon' => 'test-coupon',
        ], $payload);
    }

    /** @test */
    public function canCreateFromBasicPayloadPlanIntervalArray()
    {
        $this->assertFromPayloadToPayload();
    }

    /** @test */
    public function canCreateFromPayloadWithTrialUntilPlanIntervalArray()
    {
        $this->assertFromPayloadToPayload([
            'trialUntil' => Carbon::parse('28-01-2019')->toIso8601String(),
            'subtotal' => [
                'value' => '0.00',
                'currency' => 'EUR',
            ],
        ]);
    }

    /** @test */
    public function canCreateFromPayloadWithTrialDaysPlanIntervalArray()
    {
        $this->assertFromPayloadToPayload([
            'trialDays' => 5,
            'subtotal' => [
                'value' => '0.00',
                'currency' => 'EUR',
            ],
        ]);
    }

    /** @test */
    public function canCreateFromPayloadWithSkipTrialPlanIntervalArray()
    {
        $this->assertFromPayloadToPayload([
            'skipTrial' => true,
            'subtotal' => [
                'value' => '10.00',
                'currency' => 'EUR',
            ],
        ]);
    }

    /** @test */
    public function canCreateFromPayloadWithQuantityPlanIntervalArray()
    {
        $this->assertFromPayloadToPayload([
            'quantity' => 5,
            'subtotal' => [
                'value' => '50.00',
                'currency' => 'EUR',
            ],
        ]);
    }

    /** @test */
    public function canCreateFromPayloadWithTrialAndQuantityPlanIntervalArray()
    {
        $this->assertFromPayloadToPayload([
            'quantity' => 5,
            'trialDays' => 5,
            'subtotal' => [
                'value' => '0.00',
                'currency' => 'EUR',
            ],
        ]);
    }

    /** @test */
    public function canCreateFromPayloadWithCouponNoTrialPlanIntervalArray()
    {
        $this->withMockedCouponRepository();
        $this->assertFromPayloadToPayload([
            'coupon' => 'test-coupon',
            'subtotal' => [
                'value' => '10.00',
                'currency' => 'EUR',
            ],
        ]);
    }

    /** @test */
    public function canCreateFromPayloadWithCouponAndTrialPlanIntervalArray()
    {
        $this->withMockedCouponRepository();
        $this->assertFromPayloadToPayload([
            'coupon' => 'test-coupon',
            'trialDays' => 5,
            'subtotal' => [
                'value' => '0.00',
                'currency' => 'EUR',
            ],
        ]);
    }

    /** @test */
    public function canCreateFromPayloadWithoutTaxPercentagePlanIntervalArray()
    {
        $this->withMockedCouponRepository();
        $this->assertFromPayloadToPayload([
            'coupon' => 'test-coupon',
            'trialDays' => 5,
            'subtotal' => [
                'value' => '0.00',
                'currency' => 'EUR',
            ],
            'taxPercentage' => 0,
        ]);
    }

    /** @test */
    public function canStartDefaultSubscriptionPlanIntervalArray()
    {
        $user = $this->getMandatedUser(true, [
            'trial_ends_at' => now()->addWeek(), // on generic trial
        ]);
        $this->withMockedGetMollieCustomer();
        $this->withMockedGetMollieMandate();

        $this->assertFalse($user->subscribed('default'));

        $action = new StartSubscription(
            $user,
            'default',
            'monthly-10-1'
        );

        // Returns the OrderItem ready for processing right away.
        // Behind the scenes another OrderItem is scheduled for the next billing cycle.
        $items = $action->execute();
        $item = $items->first();
        $user = $user->fresh();
        $this->assertTrue($user->subscribed('default'));
        $this->assertFalse($user->onTrial());

        $this->assertInstanceOf(OrderItemCollection::class, $items);
        $this->assertCount(1, $items);
        $this->assertInstanceOf(OrderItem::class, $item);
        $this->assertFalse($item->isProcessed());
        $this->assertCarbon(now(), $item->process_at);
        $this->assertEquals(1000, $item->total);

        $subscription = $user->subscription('default');
        $this->assertEquals(2, $subscription->orderItems()->count());
        $this->assertCarbon(now(), $subscription->cycle_started_at);
        $this->assertCarbon(now()->addMonth(), $subscription->cycle_ends_at);

        $scheduledItem = $subscription->orderItems()->orderByDesc('process_at')->first();
        $this->assertCarbon(now()->addMonth(), $scheduledItem->process_at);
        $this->assertEquals(1000, $scheduledItem->total);
    }

    /** @test */
    public function canStartSubscriptionWithTrialDaysPlanIntervalArray()
    {
        $user = $this->getMandatedUser(true, ['tax_percentage' => 20]);
        $this->withMockedGetMollieCustomer();
        $this->withMockedGetMollieMandate();

        $this->assertFalse($user->subscribed('default'));

        $action = new StartSubscription(
            $user,
            'default',
            'monthly-10-1'
        );

        $action->trialDays(5);

        // Returns the OrderItem ready for processing right away.
        // Behind the scenes another OrderItem is scheduled for the next billing cycle.
        $items = $action->execute();
        $item = $items->first();
        $user = $user->fresh();


        $this->assertTrue($user->subscribed('default'));
        $this->assertTrue($user->onTrial());
        $this->assertInstanceOf(OrderItemCollection::class, $items);
        $this->assertCount(1, $items);
        $this->assertInstanceOf(OrderItem::class, $item);
        $this->assertFalse($item->isProcessed());
        $this->assertCarbon(now(), $item->process_at);
        $this->assertEquals(0, $item->total);
        $this->assertEquals(20, $item->tax_percentage);

        $subscription = $user->subscription('default');
        $this->assertEquals(2, $subscription->orderItems()->count());
        $this->assertCarbon(now(), $subscription->cycle_started_at);
        $this->assertCarbon(now()->addDays(5), $subscription->cycle_ends_at);

        $scheduledItem = $subscription->orderItems()->orderByDesc('process_at')->first();
        $this->assertCarbon(now()->addDays(5), $scheduledItem->process_at);

        $this->assertCarbon(
            now()->addDays(5),
            $user->subscriptions()->first()->trial_ends_at
        );
        $this->assertEquals(1000, $scheduledItem->subtotal);
        $this->assertEquals(1200, $scheduledItem->total);
        $this->assertEquals(20, $scheduledItem->tax_percentage);
    }

    /** @test */
    public function canStartSubscriptionWithTrialUntilPlanIntervalArray()
    {
        $this->withMockedGetMollieCustomer();
        $this->withMockedGetMollieMandate();
        $user = $this->getMandatedUser();


        $this->assertFalse($user->subscribed('default'));

        $action = new StartSubscription(
            $user,
            'default',
            'monthly-10-1'
        );

        $action->trialUntil(now()->addDays(5));

        // Returns the OrderItem ready for processing right away.
        // Behind the scenes another OrderItem is scheduled for the next billing cycle.
        $items = $action->execute();
        $item = $items->first();
        $user = $user->fresh();

        $this->assertTrue($user->subscribed('default'));
        $this->assertTrue($user->onTrial());
        $this->assertInstanceOf(OrderItemCollection::class, $items);
        $this->assertCount(1, $items);
        $this->assertInstanceOf(OrderItem::class, $item);
        $this->assertFalse($item->isProcessed());
        $this->assertCarbon(now(), $item->process_at);
        $this->assertEquals(0, $item->total);

        $subscription = $user->subscription('default');
        $this->assertEquals(2, $subscription->orderItems()->count());
        $this->assertCarbon(now(), $subscription->cycle_started_at);
        $this->assertCarbon(now()->addDays(5), $subscription->cycle_ends_at);

        $scheduledItem = $subscription->orderItems()->orderByDesc('process_at')->first();
        $this->assertCarbon(now()->addDays(5), $scheduledItem->process_at);

        $this->assertCarbon(
            now()->addDays(5),
            $user->subscriptions()->first()->trial_ends_at
        );
        $this->assertEquals(1000, $scheduledItem->total);
    }

    /** @test */
    public function canStartSubscriptionWithFixedInterval()
    {
        $this->withMockedGetMollieCustomer();
        $this->withMockedGetMollieMandate();
        $user = $this->getMandatedUser();

        $this->assertFalse($user->subscribed('default'));

        $action = new StartSubscription(
            $user,
            'default',
            'withixedinterval-10-1'
        );

        $items = $action->execute();
        $item = $items->first();
        $user = $user->fresh();

        $this->assertTrue($user->subscribed('default'));
        $this->assertFalse($user->onTrial());
        $subscription = $user->subscription('default');
        $this->assertEquals(1, $subscription->quantity);
        $this->assertCarbon(now(), $subscription->cycle_started_at);
        $this->assertCarbon(now()->addMonthNoOverflow(), $subscription->cycle_ends_at);
    }

    /** @test */
    public function canStartSubscriptionWithoutFixedInterval()
    {
        $this->withMockedGetMollieCustomer();
        $this->withMockedGetMollieMandate();
        $user = $this->getMandatedUser();

        $this->assertFalse($user->subscribed('default'));

        $action = new StartSubscription(
            $user,
            'default',
            'withoutfixedinterval-10-1'
        );

        $items = $action->execute();
        $item = $items->first();
        $user = $user->fresh();

        $this->assertTrue($user->subscribed('default'));
        $this->assertFalse($user->onTrial());
        $subscription = $user->subscription('default');
        $this->assertEquals(1, $subscription->quantity);
        $this->assertCarbon(now(), $subscription->cycle_started_at);
        $this->assertCarbon(now()->addMonth(), $subscription->cycle_ends_at);
    }

    /**
     * Check if the action can be built using the payload, and then can return the same payload.
     *
     * @param array $overrides
     * @throws \Exception
     */
    protected function assertFromPayloadToPayload($overrides = [])
    {
        $payload = array_filter(array_merge([
            'handler' => StartSubscription::class,
            'description' => 'Monthly payment',
            'subtotal' => [
                'value' => '10.00',
                'currency' => 'EUR',
            ],
            'taxPercentage' => 20,
            'plan' => 'monthly-10-1',
            'name' => 'default',
            'quantity' => 1,
        ], $overrides));

        $action = StartSubscription::createFromPayload(
            $payload,
            $this->getMandatedUser()
        );

        $result = $action->getPayload();

        $this->assertEquals($payload, $result);
    }

    protected function withMockedGetMollieCustomer($customerId = 'cst_unique_customer_id', $times = 1): void
    {
        $this->mock(GetMollieCustomer::class, function ($mock) use ($customerId, $times) {
            $customer = new Customer(new MollieApiClient);
            $customer->id = $customerId;

            return $mock->shouldReceive('execute')->with($customerId)->times($times)->andReturn($customer);
        });
    }

    protected function withMockedGetMollieMandate($attributes = [[
        'mandateId' => 'mdt_unique_mandate_id',
        'customerId' => 'cst_unique_customer_id',
    ]], $times = 1): void
    {
        $this->mock(GetMollieMandate::class, function ($mock) use ($times, $attributes) {
            foreach ($attributes as $data) {
                $mandate = new Mandate(new MollieApiClient);
                $mandate->id = $data['mandateId'];
                $mandate->status = 'valid';
                $mandate->method = 'directdebit';

                $mock->shouldReceive('execute')->with($data['customerId'], $data['mandateId'])->times($times)->andReturn($mandate);
            }

            return $mock;
        });
    }
}
