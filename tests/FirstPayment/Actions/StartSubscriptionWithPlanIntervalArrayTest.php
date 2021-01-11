<?php

namespace Laravel\Cashier\Tests\FirstPayment\Actions;

use Laravel\Cashier\FirstPayment\Actions\StartSubscription;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate;
use Laravel\Cashier\Mollie\GetMollieCustomer;
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

    /** @test */
    public function canStartSubscriptionWithFixedIntervalTest()
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
