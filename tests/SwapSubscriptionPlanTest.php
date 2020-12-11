<?php

namespace Laravel\Cashier\Tests;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\SubscriptionPlanSwapped;
use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate;
use Laravel\Cashier\Mollie\Contracts\GetMollieMethodMinimumAmount;
use Laravel\Cashier\Mollie\GetMollieCustomer;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Subscription;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;

class SwapSubscriptionPlanTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withPackageMigrations();
        $this->withTestNow('2019-1-1');
        $this->withConfiguredPlans();

        Event::fake();
    }

    /** @test */
    public function canSwapToAnotherPlan()
    {
        $now = now();
        $this->withMockedGetMollieCustomer();
        $this->withMockedGetMollieMandate();
        $this->withMockedGetMollieMethodMinimumAmount();
        $this->withMockedCreateMolliePayment();

        $user = $this->getUserWithZeroBalance();
        $subscription = $this->getSubscriptionForUser($user);
        $original_order_item = $subscription->scheduleNewOrderItemAt($now->copy()->subWeeks(2));

        $this->assertTrue($subscription->scheduledOrderItem->is($original_order_item));

        // Swap to new plan
        $subscription = $subscription->swap('weekly-20-1')->fresh();

        $this->assertEquals('weekly-20-1', $subscription->plan);

        $this->assertCarbon($now->copy(), $subscription->cycle_started_at);
        $this->assertCarbon($now->copy()->addWeek(), $subscription->cycle_ends_at);

        // Assert that the original scheduled OrderItem has been removed
        $this->assertFalse(OrderItem::whereId($original_order_item->id)->exists());

        // Assert that another OrderItem was scheduled for the new subscription plan
        $new_order_item = $subscription->scheduledOrderItem;
        $this->assertFalse($new_order_item->is($original_order_item));
        $this->assertCarbon($now->copy()->addWeek(), $new_order_item->process_at, 1);
        $this->assertMoneyEURCents(2200, $new_order_item->getTotal());
        $this->assertMoneyEURCents(200, $new_order_item->getTax());
        $this->assertEquals('Twice as expensive monthly subscription', $new_order_item->description);
        $this->assertFalse($new_order_item->isProcessed());

        // Assert that the amount "overpaid" for the old plan results in an additional OrderItem with negative total_amount
        $credit_item = OrderItem::where('unit_price', '<', 0)->first();
        $this->assertNotNull($credit_item);
        $this->assertCarbon($now->copy(), $credit_item->process_at, 1);
        $this->assertMoneyEURCents(-603, $credit_item->getTotal());
        $this->assertMoneyEURCents(-55, $credit_item->getTax());
        $this->assertEquals('Monthly payment', $credit_item->description);
        $this->assertTrue($credit_item->isProcessed());

        // Assert that one OrderItem has already been processed
        $processed_item = OrderItem::whereNotIn('id', [
            $new_order_item->id,
            $original_order_item->id,
            $credit_item->id,
        ])->first();
        $this->assertNotNull($processed_item);
        $this->assertCarbon($now->copy(), $processed_item->process_at, 1);
        $this->assertMoneyEURCents(2200, $processed_item->getTotal());
        $this->assertMoneyEURCents(200, $processed_item->getTax());
        $this->assertEquals('Twice as expensive monthly subscription', $processed_item->description);
        $this->assertTrue($processed_item->isProcessed());

        Event::assertDispatched(SubscriptionPlanSwapped::class, function (SubscriptionPlanSwapped $event) use ($subscription) {
            return $subscription->is($event->subscription);
        });

        $new_order_item->process();

        $subscription = $subscription->fresh();
        $this->assertCarbon($now->copy()->addWeek(), $subscription->cycle_started_at);
        $this->assertCarbon($now->copy()->addWeeks(2), $subscription->cycle_ends_at);

        $scheduled_order_item = $subscription->scheduledOrderItem;
        $this->assertCarbon($now->copy()->addWeeks(2), $scheduled_order_item->process_at, 1);
        $this->assertMoneyEURCents(2200, $scheduled_order_item->getTotal());
        $this->assertMoneyEURCents(200, $scheduled_order_item->getTax());
        $this->assertEquals('Twice as expensive monthly subscription', $scheduled_order_item->description);
        $this->assertFalse($scheduled_order_item->isProcessed());
    }

    /** @test */
    public function swappingACancelledSubscriptionResumesIt()
    {
        $subscription = $this->getUser()->subscriptions()->save(
            factory(Subscription::class)->make([
                'ends_at' => now()->addWeek(),
                'plan' => 'monthly-20-1',
            ])
        );
        $subscription->cancel();

        $this->assertTrue($subscription->cancelled());

        $subscription->swap('weekly-20-1', false);

        $this->assertFalse($subscription->cancelled());
    }

    /** @test */
    public function canSwapNextCycle()
    {
        $user = $this->getUserWithZeroBalance();
        $subscription = $this->getSubscriptionForUser($user);
        $original_order_item = $subscription->scheduleNewOrderItemAt(now()->subWeeks(2));

        $this->assertTrue($subscription->scheduledOrderItem->is($original_order_item));

        // Swap to new plan
        $subscription = $subscription->swapNextCycle('weekly-20-1')->fresh();

        $this->assertEquals('monthly-10-1', $subscription->plan);
        $this->assertEquals('weekly-20-1', $subscription->next_plan);

        // Check that the billing cycle remains intact
        $cycle_should_have_started_at = now()->subWeeks(2);
        $cycle_should_end_at = $cycle_should_have_started_at->copy()->addMonth();
        $this->assertCarbon($cycle_should_have_started_at, $subscription->cycle_started_at);
        $this->assertCarbon($cycle_should_end_at, $subscription->cycle_ends_at);

        // Assert that the original scheduled OrderItem has been removed
        // And assert that another OrderItem was scheduled for the new subscription plan
        $this->assertFalse(OrderItem::whereId($original_order_item->id)->exists());
        $new_order_item = $subscription->scheduledOrderItem;
        $this->assertFalse($new_order_item->is($original_order_item));
        $this->assertCarbon($cycle_should_end_at, $new_order_item->process_at, 1); // based on previous plan's cycle
        $this->assertEquals(2200, $new_order_item->total);
        $this->assertEquals(200, $new_order_item->tax);
        $this->assertEquals('Twice as expensive monthly subscription', $new_order_item->description);

        $this->assertFalse($user->fresh()->hasCredit());

        Event::assertNotDispatched(SubscriptionPlanSwapped::class);

        $this->assertEquals('monthly-10-1', $subscription->plan);
        $this->assertEquals('weekly-20-1', $subscription->next_plan);

        Subscription::processOrderItem($new_order_item);

        $subscription = $subscription->fresh();

        $this->assertNull($subscription->next_plan);
        $this->assertEquals('weekly-20-1', $subscription->plan);

        // Assert that the subscription cycle reflects the new plan
        $cycle_should_have_started_at = $cycle_should_end_at->copy();
        $cycle_should_end_at = $cycle_should_have_started_at->copy()->addWeek();
        $this->assertCarbon($cycle_should_have_started_at, $subscription->cycle_started_at);
        $this->assertCarbon($cycle_should_end_at, $subscription->cycle_ends_at);

        Event::assertDispatched(SubscriptionPlanSwapped::class, function (SubscriptionPlanSwapped $event) use ($subscription) {
            return $subscription->is($event->subscription);
        });
    }

    protected function getUserWithZeroBalance()
    {
        $user = $this->getMandatedUser(true, [
            'tax_percentage' => 10,
            'mollie_customer_id' => 'cst_unique_customer_id',
            'mollie_mandate_id' => 'mdt_unique_mandate_id',
        ]);
        $this->assertEquals(0, $user->credits()->whereCurrency('EUR')->count());

        return $user;
    }

    /**
     * @param $user
     * @return Subscription
     */
    protected function getSubscriptionForUser($user)
    {
        return $user->subscriptions()->save(factory(Subscription::class)->make([
            "name" => "dummy name",
            "plan" => "monthly-10-1",
            "cycle_started_at" => now()->subWeeks(2),
            "cycle_ends_at" => now()->subWeeks(2)->addMonth(),
            "tax_percentage" => 10,
        ]));
    }

    protected function withMockedGetMollieCustomer($customerIds = ['cst_unique_customer_id'], $times = 1): void
    {
        $this->mock(GetMollieCustomer::class, function ($mock) use ($customerIds, $times) {
            foreach ($customerIds as $id) {
                $customer = new Customer(new MollieApiClient);
                $customer->id = $id;
                $mock->shouldReceive('execute')->with($id)->times($times)->andReturn($customer);
            }

            return $mock;
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

    protected function withMockedGetMollieMethodMinimumAmount($times = 1): void
    {
        $this->mock(GetMollieMethodMinimumAmount::class, function ($mock) use ($times) {
            return $mock->shouldReceive('execute')->with('directdebit', 'EUR')->times($times)->andReturn(money(100, 'EUR'));
        });
    }

    protected function withMockedCreateMolliePayment($times = 1): void
    {
        $this->mock(CreateMolliePayment::class, function ($mock) use ($times) {
            return $mock->shouldReceive('execute')->times($times);
        });
    }
}
