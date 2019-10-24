<?php

namespace Laravel\Cashier\Tests\Coupon;

use Laravel\Cashier\Coupon\RedeemedCoupon;
use Laravel\Cashier\Tests\BaseTestCase;

class RedeemedCouponTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function canBeRevoked()
    {
        $this->withPackageMigrations();

        /** @var RedeemedCoupon $redeemedCoupon */
        $redeemedCoupon = factory(RedeemedCoupon::class)->create(['times_left' => 5]);

        $this->assertEquals(5, $redeemedCoupon->times_left);
        $this->assertTrue($redeemedCoupon->isActive());

        $redeemedCoupon = $redeemedCoupon->revoke();

        $this->assertEquals(0, $redeemedCoupon->times_left);
        $this->assertFalse($redeemedCoupon->isActive());
    }


}
