<?php

namespace Laravel\Cashier\Tests;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\SubscriptionResumed;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate;
use Laravel\Cashier\Mollie\GetMollieCustomer;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\Fixtures\User;
use LogicException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;

class SubscriptionTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withPackageMigrations();
    }

    /** @test */
    public function canAccessOwner()
    {
        $user = factory(User::class)->create();

        $subscription = $user->subscriptions()->save(
            factory(Subscription::class)->make()
        );

        $this->assertTrue($user->is($subscription->owner));
    }

    /** @test */
    public function canAccessOrderItems()
    {
        $subscription = factory(Subscription::class)->create();

        $items = $subscription->orderItems()->save(
            factory(OrderItem::class)->make()
        );

        $this->assertNotNull($items);
    }

    /** @test */
    public function cannotScheduleNewOrderItemIfIdIsSet()
    {
        $this->expectException(LogicException::class);

        config(['cashier.plans' => [
            'monthly-10-1' => [
                'amount' => [
                    'currency' => 'EUR',
                    'value' => '10.00',
                ],
                'interval' => '1 month',
                'method' => 'directdebit',
                'description' => 'Monthly payment',
            ],
        ]]);

        $subscription = factory(Subscription::class)->create([
            'scheduled_order_item_id' => 'should_be_empty',
        ]);

        $subscription->scheduleNewOrderItemAt(now());
    }

    /** @test */
    public function cannotResumeIfNotCancelled()
    {
        $this->expectException(LogicException::class);

        $subscription = factory(Subscription::class)->create();

        $this->assertFalse($subscription->cancelled());

        Event::fake();

        $subscription->resume();

        Event::assertNotDispatched(SubscriptionResumed::class);
    }

    /** @test */
    public function cannotResumeIfNotOnGracePeriod()
    {
        $this->expectException(LogicException::class);

        $subscription = factory(Subscription::class)->create([
            'ends_at' => now()->subMonth(),
        ]);

        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());

        Event::fake();

        $subscription->resume();

        Event::assertNotDispatched(SubscriptionResumed::class);
    }

    /** @test */
    public function getCycleProgressTest()
    {
        $now = now();
        $completed_subscription = factory(Subscription::class)->make([
            'cycle_started_at' => $now->copy()->subMonth(),
            'cycle_ends_at' => $now->copy()->subDay(),
        ]);

        $progressing_subscription = factory(Subscription::class)->make([
            'cycle_started_at' => $now->copy()->subDays(3),
            'cycle_ends_at' => $now->copy()->addDays(3),
        ]);

        $unstarted_subscription = factory(Subscription::class)->make([
            'cycle_started_at' => $now->copy()->addDays(3),
            'cycle_ends_at' => $now->copy()->addMonth(),
        ]);

        $this->assertEquals(1, $completed_subscription->cycle_progress);
        $this->assertEquals(0, $unstarted_subscription->cycle_progress);
        $this->assertEquals(0.5, $progressing_subscription->cycle_progress);
    }

    /** @test */
    public function testSyncTaxPercentage()
    {
        $user = factory(User::class)->create();
        $this->assertEquals(0, $user->taxPercentage());

        $subscription = factory(Subscription::class)->create([
            'tax_percentage' => 21.5,
        ]);

        $subscription->syncTaxPercentage();

        $this->assertEquals(0, $subscription->tax_percentage);
    }

    /** @test */
    public function yieldsOrderItemsAtSetIntervals()
    {
        Carbon::setTestNow(Carbon::parse('2018-01-01'));
        $this->withConfiguredPlans();
        $this->withMockedGetMollieCustomer();
        $this->withMockedGetMollieMandate();

        $user = factory(User::class)->create([
            'mollie_mandate_id' => 'mdt_unique_mandate_id',
            'mollie_customer_id' => 'cst_unique_customer_id',
        ]);

        $subscription = $user->newSubscriptionForMandateId('mdt_unique_mandate_id', 'main', 'monthly-10-1')->create();
        $this->assertCarbon(Carbon::parse('2018-01-01'), $subscription->cycle_started_at);
        $this->assertCarbon(Carbon::parse('2018-01-01'), $subscription->cycle_ends_at);

        $item_1 = $subscription->scheduledOrderItem;
        $this->assertNotNull($item_1);
        $this->assertSame("2018-01-01 00:00:00", $item_1->process_at->toDateTimeString());
        $this->assertSame("Laravel\Cashier\Subscription", $item_1->orderable_type);
        $this->assertSame("Laravel\Cashier\Tests\Fixtures\User", $item_1->owner_type);
        $this->assertSame("1", $item_1->orderable_id);
        $this->assertSame("1", $item_1->owner_id);
        $this->assertSame("Monthly payment", $item_1->description);
        $this->assertSame(null, $item_1->description_extra_lines);
        $this->assertSame("EUR", $item_1->currency);
        $this->assertSame("1", $item_1->quantity);
        $this->assertSame("1000", $item_1->unit_price);
        $this->assertSame("0", $item_1->tax_percentage);
        $this->assertSame(null, $item_1->order_id);

        $item_1->process();

        $subscription = $subscription->fresh('scheduledOrderItem');
        $this->assertCarbon(Carbon::parse('2018-01-01'), $subscription->cycle_started_at);
        $this->assertCarbon(Carbon::parse('2018-02-01'), $subscription->cycle_ends_at);

        $this->assertEquals([
            'From 2018-01-01 to 2018-02-01',
        ], $item_1->description_extra_lines);

        $item_2 = $subscription->scheduledOrderItem;

        $this->assertSame("2018-02-01 00:00:00", $item_2->process_at->toDateTimeString());
        $this->assertSame("Laravel\Cashier\Subscription", $item_2->orderable_type);
        $this->assertSame("Laravel\Cashier\Tests\Fixtures\User", $item_2->owner_type);
        $this->assertSame("1", $item_2->orderable_id);
        $this->assertSame("1", $item_2->owner_id);
        $this->assertSame("Monthly payment", $item_2->description);
        $this->assertSame(null, $item_2->description_extra_lines);
        $this->assertSame("EUR", $item_2->currency);
        $this->assertSame("1", $item_2->quantity);
        $this->assertSame("1000", $item_2->unit_price);
        $this->assertSame("0", $item_2->tax_percentage);
        $this->assertSame(null, $item_2->order_id);


        $item_2->process();

        $subscription = $subscription->fresh('scheduledOrderItem');
        $this->assertCarbon(Carbon::parse('2018-02-01'), $subscription->cycle_started_at);
        $this->assertCarbon(Carbon::parse('2018-03-01'), $subscription->cycle_ends_at);

        $this->assertEquals([
            'From 2018-02-01 to 2018-03-01',
        ], $item_2->description_extra_lines);

        $item_3 = $subscription->scheduledOrderItem;

        $this->assertSame("2018-03-01 00:00:00", $item_3->process_at->toDateTimeString());
        $this->assertSame("Laravel\Cashier\Subscription", $item_3->orderable_type);
        $this->assertSame("Laravel\Cashier\Tests\Fixtures\User", $item_3->owner_type);
        $this->assertSame("1", $item_3->orderable_id);
        $this->assertSame("1", $item_3->owner_id);
        $this->assertSame("Monthly payment", $item_3->description);
        $this->assertSame(null, $item_3->description_extra_lines);
        $this->assertSame("EUR", $item_3->currency);
        $this->assertSame("1", $item_3->quantity);
        $this->assertSame("1000", $item_3->unit_price);
        $this->assertSame("0", $item_3->tax_percentage);
        $this->assertSame(null, $item_3->order_id);
    }

    /** @test */
    public function cancelWorks()
    {
        $cycle_ends_at = now()->addWeek();
        $user = factory(User::class)->create();
        $subscription = $user->subscriptions()->save(factory(Subscription::class)->make([
            'cycle_ends_at' => $cycle_ends_at,
        ]));

        $this->assertFalse($subscription->onTrial());
        $this->assertFalse($subscription->cancelled());
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());

        $subscription->cancel();

        $this->assertCarbon($cycle_ends_at, $subscription->ends_at);
        $this->assertNull($subscription->cycle_ends_at);
        $this->assertFalse($subscription->onTrial());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());
    }

    /** @test */
    public function cancelAtWorks()
    {
        $user = factory(User::class)->create();
        $subscription = $user->subscriptions()->save(
            factory(Subscription::class)->make([
            'cycle_ends_at' => now()->addWeek(), ])
        );

        $this->assertFalse($subscription->onTrial());
        $this->assertFalse($subscription->cancelled());
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());

        $subscription->cancelAt(now()->addDays(2));

        $this->assertCarbon(now()->addDays(2), $subscription->ends_at);
        $this->assertNull($subscription->cycle_ends_at);
        $this->assertFalse($subscription->onTrial());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());
    }

    /** @test */
    public function cancelNowWorks()
    {
        $user = factory(User::class)->create();
        $subscription = $user->subscriptions()->save(
            factory(Subscription::class)->make([
            'cycle_ends_at' => now()->addWeek(), ])
        );

        $this->assertFalse($subscription->onTrial());
        $this->assertFalse($subscription->cancelled());
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());

        $subscription->cancelNow();

        $this->assertCarbon(now(), $subscription->ends_at);
        $this->assertNull($subscription->cycle_ends_at);
        $this->assertFalse($subscription->onTrial());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
    }

    /** @test */
    public function resumingACancelledSubscriptionResetsCycleEndsAt()
    {
        $this->withConfiguredPlans();
        $user = factory(User::class)->create();

        /** @var Subscription $subscription */
        $subscription = $user->subscriptions()->save(factory(Subscription::class)->make([
            'ends_at' => now()->addWeek(),
        ]));

        $this->assertTrue($subscription->cancelled());

        $subscription->resume();

        $this->assertFalse($subscription->cancelled());
        $this->assertCarbon(now()->addWeek(), $subscription->cycle_ends_at);
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
