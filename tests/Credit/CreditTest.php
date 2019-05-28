<?php

namespace Laravel\Cashier\Tests\Credit;

use Laravel\Cashier\Credit\Credit;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Money\Money;

class CreditTest extends BaseTestCase
{
    /** @test */
    public function testAddAmountForOwner()
    {
        $this->withPackageMigrations();
        $user = factory(User::class)->create();

        Credit::addAmountForOwner($user, Money::EUR(12345));
        Credit::addAmountForOwner($user, Money::EUR(12346));
        Credit::addAmountForOwner($user, Money::USD(12348));

        $creditEUR = $user->credit('EUR');
        $this->assertEquals(24691, $creditEUR->value);
        $this->assertEquals('EUR', $creditEUR->currency);
        $this->assertTrue(Money::EUR(24691)->equals($creditEUR->money()));

        $creditUSD = $user->credit('USD');
        $this->assertEquals(12348, $creditUSD->value);
        $this->assertEquals('USD', $creditUSD->currency);
        $this->assertTrue(Money::USD(12348)->equals($creditUSD->money()));
    }

    /** @test */
    public function testMaxOutForOwner()
    {
        $this->withPackageMigrations();
        $user = factory(User::class)->create();

        Credit::addAmountForOwner($user, Money::USD(12348));
        $usedUSD = Credit::maxOutForOwner($user, Money::USD(20025));

        $this->assertEquals(Money::USD(12348), $usedUSD);
        $this->assertEquals(0, Credit::whereOwner($user)->whereCurrency('USD')->first()->value);

        Credit::addAmountForOwner($user, Money::EUR(12346));
        $usedEUR = Credit::maxOutForOwner($user, Money::EUR(510));

        $this->assertTrue(Money::EUR(510)->equals($usedEUR));
        $this->assertEquals(11836, Credit::whereOwner($user)->whereCurrency('EUR')->first()->value);
    }
}
