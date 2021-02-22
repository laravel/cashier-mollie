<?php

namespace Laravel\Cashier\Tests;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\SubscriptionQuantityUpdated;
use Laravel\Cashier\Events\SubscriptionResumed;
use Laravel\Cashier\Events\SubscriptionStarted;
use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate;
use Laravel\Cashier\Mollie\Contracts\GetMollieMethodMinimumAmount;
use Laravel\Cashier\Mollie\GetMollieCustomer;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\Fixtures\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;
use Mollie\Api\Resources\Payment;

class ManageSubscriptionTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withPackageMigrations();
        $this->withConfiguredPlans();
    }

    /**
     * Assert that a new subscription and its order items can be created and processed.
     *
     * @test
     */
    public function canCreateDirectDebitSubscriptionForMandatedCustomer()
    {
        $this->withMockedGetMollieCustomer('cst_unique_customer_id', 5);
        $this->withMockedGetMollieMandate([[
            'mandateId' => 'mdt_unique_mandate_id',
            'customerId' => 'cst_unique_customer_id',
        ]], 5);
        $this->withMockedGetMollieMethodMinimumAmount(4);
        $this->withMockedCreateMolliePayment(4);

        $user = $this->getMandatedUser(true, [
            'mollie_mandate_id' => 'mdt_unique_mandate_id',
            'mollie_customer_id' => 'cst_unique_customer_id',
            'tax_percentage' => 10,
            'trial_ends_at' => now()->addWeek(),
        ]);

        $this->assertEquals(0, $user->subscriptions()->count());
        $this->assertEquals(0, $user->orderItems()->count());
        $this->assertTrue($user->onGenericTrial());

        Event::fake();

        $user->newSubscriptionForMandateId('mdt_unique_mandate_id', 'main', 'monthly-10-1')->create();

        $subscription = $user->subscription('main')->fresh();

        Event::assertDispatched(SubscriptionStarted::class, function (SubscriptionStarted $e) use ($subscription) {
            $this->assertTrue($e->subscription->is($subscription));

            return true;
        });

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull($user->mollie_customer_id);
        $this->assertFalse($user->onGenericTrial());
        $this->assertNotNull($user->subscription('main'));
        $this->assertTrue($user->subscribed('main'));
        $this->assertFalse($user->subscribed('invalid'));
        $this->assertTrue($user->subscribedToPlan('monthly-10-1', 'main'));
        $this->assertFalse($user->subscribedToPlan('monthly-10-1', 'something'));
        $this->assertFalse($user->subscribedToPlan('monthly-10-2', 'main'));
        $this->assertFalse($user->subscribedToPlan('non-existent-plan', 'main'));
        $this->assertTrue($user->subscribed('main', 'monthly-10-1'));
        $this->assertFalse($user->subscribed('main', 'monthly-10-2'));
        $this->assertTrue($user->subscription('main')->active());
        $this->assertFalse($user->subscription('main')->cancelled());
        $this->assertFalse($user->subscription('main')->onGracePeriod());
        $this->assertTrue($user->subscription('main')->recurring());
        $this->assertFalse($user->subscription('main')->ended());

        $this->assertEquals(1, $user->subscription('main')->orderItems()->count());
        $this->assertEquals(1, $user->orderItems()->count());

        $this->assertCarbon(now(), $user->subscription('main')->cycle_ends_at);

        $scheduled_order_item = $user->subscription('main')->scheduledOrderItem;
        $this->assertNotNull($scheduled_order_item);
        $this->assertInstanceOf(OrderItem::class, $scheduled_order_item);
        $this->assertTrue($scheduled_order_item->is($user->subscription('main')->orderItems()->first()));
        $this->assertEquals('EUR', $scheduled_order_item->currency);
        $this->assertEquals($user->id, $scheduled_order_item->owner_id);
        $this->assertEquals(get_class($user), $scheduled_order_item->owner_type);
        $this->assertCarbon(now(), $scheduled_order_item->process_at, 5);
        $this->assertEquals(1, $scheduled_order_item->quantity);
        $this->assertEquals(1000, $scheduled_order_item->unit_price);
        $this->assertEquals(1000, $scheduled_order_item->subtotal);
        $this->assertEquals(10, $scheduled_order_item->tax_percentage);
        $this->assertEquals('Monthly payment', $scheduled_order_item->description);
        $this->assertFalse($scheduled_order_item->isProcessed());

        $this->assertEquals(0, $user->orders()->count());

        $previously_scheduled_order_item = $scheduled_order_item;

        Cashier::run(); // process open order items into orders

        $this->assertTrue($previously_scheduled_order_item->fresh()->isProcessed());

        // assert that an order was created properly
        $this->assertEquals(1, $user->orders()->count());
        $order = $user->orders()->first();
        $this->assertEquals(2, OrderItem::count());
        $this->assertEquals(1, $order->items()->count());
        $this->assertTrue($previously_scheduled_order_item->is($order->items()->first()));
        $this->assertNotNull($order->mollie_payment_id);
        $this->assertEquals('open', $order->mollie_payment_status);

        $subscription = $user->subscription('main')->fresh();
        $this->assertCarbon(now()->addMonth(), $subscription->cycle_ends_at);

        // assert that a new order item was scheduled
        $scheduled_order_item = $subscription->scheduled_order_item;
        $this->assertEquals('EUR', $scheduled_order_item->currency);
        $this->assertEquals($user->id, $scheduled_order_item->owner_id);
        $this->assertEquals(get_class($user), $scheduled_order_item->owner_type);
        $this->assertCarbon(now()->addMonth(), $scheduled_order_item->process_at);
        $this->assertEquals(1000, $scheduled_order_item->unit_price);
        $this->assertEquals(10, $scheduled_order_item->tax_percentage);
        $this->assertEquals(1, $scheduled_order_item->quantity);
        $this->assertEquals(1000, $scheduled_order_item->subtotal);
        $this->assertEquals(100, $scheduled_order_item->tax);
        $this->assertEquals(1100, $scheduled_order_item->total);
        $this->assertFalse($scheduled_order_item->isProcessed());

        // Cancel Subscription
        $subscription = $subscription->cancel()->fresh();

        $this->assertNull($subscription->cycle_ends_at);
        $this->assertNull($subscription->scheduled_order_item);
        $this->assertEquals(1, $subscription->orderItems()->count());

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Modify Ends Date To Past
        $oldGracePeriod = $subscription->ends_at;
        $subscription->fill(['ends_at' => Carbon::now()->subDays(5)])->save();

        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertTrue($subscription->ended());

        $subscription->fill(['ends_at' => $oldGracePeriod])->save();

        // Resume Subscription
        Event::fake();

        $old_subscription = $subscription->fresh();
        $subscription = $subscription->resume()->fresh();

        Event::assertDispatched(SubscriptionResumed::class, function (SubscriptionResumed $e) use ($subscription) {
            $this->assertTrue($e->subscription->is($subscription));

            return true;
        });

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        $this->assertEquals($old_subscription->ends_at, $subscription->cycle_ends_at);
        $scheduled_order_item = $subscription->scheduled_order_item;
        $this->assertEquals($subscription->cycle_ends_at, $scheduled_order_item->process_at);


        // Quantity Increment & Decrement
        Event::fake();
        $this->assertEquals(1, $subscription->quantity);

        $subscription->incrementQuantity();

        $this->assertEquals(2, $subscription->quantity);
        Event::assertDispatched(SubscriptionQuantityUpdated::class, function (SubscriptionQuantityUpdated $e) use ($subscription) {
            $this->assertTrue($e->subscription->is($subscription));
            $this->assertEquals(2, $e->subscription->quantity);
            $this->assertEquals(1, $e->oldQuantity);

            return true;
        });

        Event::fake();
        $subscription->decrementQuantity();

        $this->assertEquals(1, $subscription->quantity);
        Event::assertDispatched(SubscriptionQuantityUpdated::class, function (SubscriptionQuantityUpdated $e) use ($subscription) {
            $this->assertTrue($e->subscription->is($subscription));
            $this->assertEquals(1, $e->subscription->quantity);
            $this->assertEquals(2, $e->oldQuantity);

            return true;
        });

        // Swap Plan
        $subscription = $subscription->swap('monthly-10-2')->fresh();

        $this->assertEquals('monthly-10-2', $subscription->plan);
        // Other swap characteristics are covered in SwapSubscriptionPlanTest.php

        // Invoice Tests
        $invoice = $user->invoices()[0];

        $this->assertEquals('11,00 €', $invoice->total());
        $this->assertFalse($invoice->hasStartingBalance());
        $this->assertInstanceOf(Carbon::class, $invoice->date());
    }

    public function testCreatingSubscriptionWithTrial()
    {
        $this->withMockedGetMollieCustomer('cst_unique_customer_id', 1);
        $this->withMockedGetMollieMandate([[
            'mandateId' => 'mdt_unique_mandate_id',
            'customerId' => 'cst_unique_customer_id',
        ]], 1);

        $user = $this->getMandatedUser(true, [
            'mollie_mandate_id' => 'mdt_unique_mandate_id',
            'mollie_customer_id' => 'cst_unique_customer_id',
            'tax_percentage' => 10,
            'trial_ends_at' => now()->addWeek(),
        ]);

        // Create Subscription
        $user->newSubscriptionForMandateId('mdt_unique_mandate_id', 'main', 'monthly-10-1')
            ->trialDays(7)
            ->create();

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertCarbon(now()->addDays(7), $subscription->trial_ends_at);
        $this->assertCarbon(now()->addDays(7), $subscription->cycle_ends_at);
        $this->assertTrue($user->onTrial('main', 'monthly-10-1'));
        $this->assertTrue($user->onTrial('main'));

        $item = $subscription->scheduledOrderItem;
        $this->assertCarbon(now()->addDays(7), $item->process_at);

        // Cancel Subscription
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertTrue($user->onTrial('main', 'monthly-10-1'));
        $this->assertTrue($user->onTrial('main'));

        $this->assertEquals(0, OrderItem::count());

        // Resume Subscription
        Event::fake();

        $subscription->resume();

        Event::assertDispatched(SubscriptionResumed::class, function (SubscriptionResumed $e) use ($subscription) {
            $this->assertTrue($e->subscription->is($subscription));

            return true;
        });

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertCarbon(now()->addDays(7), $subscription->trial_ends_at);
        $this->assertTrue($user->onTrial('main', 'monthly-10-1'));
        $this->assertTrue($user->onTrial('main'));

        $item = $subscription->scheduledOrderItem;
        $this->assertCarbon(now()->addDays(7), $item->process_at);
    }

    public function testGenericTrials()
    {
        $user = new User;
        $this->assertFalse($user->onGenericTrial());
        $this->assertFalse($user->onTrial());
        $user->trial_ends_at = Carbon::tomorrow();
        $this->assertTrue($user->onGenericTrial());
        $this->assertTrue($user->onTrial());
        $user->trial_ends_at = Carbon::today()->subDays(5);
        $this->assertFalse($user->onGenericTrial());
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

    protected function withMockedGetMollieMethodMinimumAmount($times = 1): void
    {
        $this->mock(GetMollieMethodMinimumAmount::class, function ($mock) use ($times) {
            return $mock->shouldReceive('execute')->with('directdebit', 'EUR')->times($times)->andReturn(money(100, 'EUR'));
        });
    }

    protected function withMockedCreateMolliePayment($times = 1): void
    {
        $this->mock(CreateMolliePayment::class, function ($mock) use ($times) {
            $payment = new Payment(new MollieApiClient);
            $payment->id = 'tr_unique_payment_id';
            $payment->amount = (object) [
                'currency' => 'EUR',
                'value' => '10.00',
            ];
            $payment->mandateId = 'mdt_dummy_mandate_id';

            return $mock->shouldReceive('execute')->times($times)->andReturn($payment);
        });
    }
}
