<?php

namespace Laravel\Cashier\Tests\SubscriptionBuilder;

use Carbon\Carbon;
use Laravel\Cashier\Coupon\AppliedCoupon;
use Laravel\Cashier\Coupon\RedeemedCoupon;
use Laravel\Cashier\Exceptions\CouponException;
use Laravel\Cashier\Mollie\Contracts\GetMollieCustomer;
use Laravel\Cashier\Mollie\Contracts\GetMollieMandate;
use Laravel\Cashier\SubscriptionBuilder\MandatedSubscriptionBuilder;
use Laravel\Cashier\Tests\BaseTestCase;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;

class MandatedSubscriptionBuilderTest extends BaseTestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withPackageMigrations();
        $this->withConfiguredPlans();
        $this->user = $this->getCustomerUser(true, [
            'tax_percentage' => 20,
            'mollie_customer_id' => 'cst_unique_customer_id',
            'mollie_mandate_id' => 'mdt_unique_mandate_id',
        ]);
    }

    /** @test */
    public function testWithCouponNoTrial()
    {
        $this->withMockedCouponRepository();
        $this->withMockedGetMollieMandate();
        $this->withMockedGetMollieCustomer();
        $now = Carbon::parse('2019-01-01');
        $this->withTestNow($now);

        $this->assertEquals(0, RedeemedCoupon::count());
        $this->assertEquals(0, AppliedCoupon::count());

        $builder = $this->getBuilder();

        $subscription = $builder->withCoupon('test-coupon')->create();

        $this->assertEquals(1, $subscription->redeemedCoupons()->count());

        // Coupons will be applied when (pre)processing the Subscription OrderItems
        $this->assertEquals(0, $subscription->appliedCoupons()->count());

        $orderItem = $subscription->orderItems()->first();
        $this->assertCarbon($now, $orderItem->process_at);
        $this->assertEquals('EUR', $orderItem->currency);
        $this->assertEquals(1000, $orderItem->unit_price);
        $this->assertEquals(1, $orderItem->quantity);
    }

    public function testWithCouponAndTrial()
    {
        $this->withMockedCouponRepository();
        $this->withMockedGetMollieMandate();
        $this->withMockedGetMollieCustomer();
        $now = Carbon::parse('2019-01-01');
        $this->withTestNow($now);

        $this->assertEquals(0, RedeemedCoupon::count());
        $this->assertEquals(0, AppliedCoupon::count());

        $builder = $this->getBuilder();

        $subscription = $builder
            ->withCoupon('test-coupon')
            ->trialDays(5)
            ->create();

        $this->assertEquals(1, $subscription->redeemedCoupons()->count());

        // Coupons will be applied when (pre)processing the Subscription OrderItems
        $this->assertEquals(0, $subscription->appliedCoupons()->count());

        $orderItem = $subscription->orderItems()->first();
        $this->assertCarbon($now->copy()->addDays(5), $orderItem->process_at);
        $this->assertEquals('EUR', $orderItem->currency);
        $this->assertEquals(1000, $orderItem->unit_price);
        $this->assertEquals(1, $orderItem->quantity);
    }

    /** @test */
    public function testWithCouponValidatesCoupon()
    {
        $this->expectException(CouponException::class);
        $this->withMockedCouponRepository(null, new InvalidatingCouponHandler);
        $this->withMockedGetMollieMandate();
        $this->withMockedGetMollieCustomer();
        $this->getBuilder()->withCoupon('test-coupon')->create();
    }

    /** @test */
    public function testSkipTrialWorks()
    {
        $builder = $this->getBuilder()->trialDays(5);
        $this->assertTrue($builder->makeSubscription()->onTrial());

        $builder->skipTrial();
        $this->assertFalse($builder->makeSubscription()->onTrial());
    }


    /**
     * @return \Laravel\Cashier\SubscriptionBuilder\MandatedSubscriptionBuilder
     */
    protected function getBuilder()
    {
        return new MandatedSubscriptionBuilder(
            $this->user,
            'default',
            'monthly-10-1'
        );
    }

    protected function withMockedGetMollieCustomer(): void
    {
        $this->mock(GetMollieCustomer::class, function ($mock) {
            $customer = new Customer(new MollieApiClient);
            $customer->id = 'cst_unique_customer_id';

            return $mock->shouldReceive('execute')->with('cst_unique_customer_id')->once()->andReturn($customer);
        });
    }

    protected function withMockedGetMollieMandate(): void
    {
        $this->mock(GetMollieMandate::class, function ($mock) {
            $mandate = new Mandate(new MollieApiClient);
            $mandate->id = 'mdt_unique_mandate_id';
            $mandate->status = 'valid';
            $mandate->method = 'directdebit';

            return $mock->shouldReceive('execute')->with('cst_unique_customer_id', 'mdt_unique_mandate_id')->once()->andReturn($mandate);
        });
    }
}
