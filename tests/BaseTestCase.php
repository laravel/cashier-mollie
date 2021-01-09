<?php

namespace Laravel\Cashier\Tests;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Laravel\Cashier\Coupon\Contracts\CouponRepository;
use Laravel\Cashier\Coupon\Coupon;
use Laravel\Cashier\Coupon\FixedDiscountHandler;
use Laravel\Cashier\Tests\Database\Migrations\CreateUsersTable;
use Laravel\Cashier\Tests\Fixtures\User;
use Mockery;
use Mollie\Api\MollieApiClient;
use Mollie\Laravel\Wrappers\MollieApiWrapper;
use Money\Money;
use Orchestra\Testbench\TestCase;

abstract class BaseTestCase extends TestCase
{
    protected $interactWithMollieAPI = false;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withFixtureModels();
        $this->withFactories(__DIR__.'/database/factories');

        config(['cashier.webhook_url' => 'https://www.example.com/webhook']);
        config(['cashier.first_payment.webhook_url' => 'https://www.example.com/mandate-webhook']);

        if (! $this->interactWithMollieAPI) {
            // Disable the Mollie API
            $this->mock(MollieApiWrapper::class, null);
        }
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return ['Laravel\Cashier\CashierServiceProvider'];
    }

    /**
     * Execute table migrations.
     * @return $this
     */
    protected function withPackageMigrations()
    {
        $migrations_dir = __DIR__.'/../database/migrations';

        $this->runMigrations(
            collect(
                [
                    [
                        'class' => CreateUsersTable::class,
                        'file_path' => __DIR__ . '/database/migrations/create_users_table.php',
                    ],
                    [
                        'class' => '\CreateSubscriptionsTable',
                        'file_path' => $migrations_dir . '/create_subscriptions_table.php.stub',
                    ],
                    [
                        'class' => '\CreateOrderItemsTable',
                        'file_path' => $migrations_dir . '/create_order_items_table.php.stub',
                    ],
                    [
                        'class' => '\CreateOrdersTable',
                        'file_path' => $migrations_dir . '/create_orders_table.php.stub',
                    ],
                    [
                        'class' => '\CreateCreditsTable',
                        'file_path' => $migrations_dir . '/create_credits_table.php.stub',
                    ],
                    [
                        'class' => '\CreateRedeemedCouponsTable',
                        'file_path' => $migrations_dir . '/create_redeemed_coupons_table.php.stub',
                    ],
                    [
                        'class' => '\CreateAppliedCouponsTable',
                        'file_path' => $migrations_dir . '/create_applied_coupons_table.php.stub',
                    ],
                ]
            )
        );

        return $this;
    }

    /**
     * Runs a collection of migrations.
     *
     * @param Collection $migrations
     */
    protected function runMigrations(Collection $migrations)
    {
        $migrations->each(function ($migration) {
            $this->runMigration($migration['class'], $migration['file_path']);
        });
    }

    /**
     * @param string $class
     * @param string $file_path
     */
    protected function runMigration($class, $file_path)
    {
        include_once $file_path;
        (new $class)->up();
    }

    /**
     * Assert that a Carbon datetime is approximately equal to another Carbon datetime.
     *
     * @param \Carbon\Carbon $expected
     * @param \Carbon\Carbon $actual
     * @param int $precision_seconds
     */
    protected function assertCarbon(Carbon $expected, Carbon $actual, int $precision_seconds = 5)
    {
        $expected_min = $expected->copy()->subSeconds($precision_seconds)->startOfSecond();
        $expected_max = $expected->copy()->addSeconds($precision_seconds)->startOfSecond();

        $actual = $actual->copy()->startOfSecond();

        $this->assertTrue(
            $actual->between($expected_min, $expected_max),
            "Actual datetime [{$actual}] differs more than {$precision_seconds} seconds from expected [{$expected}]."
        );
    }

    /**
     * @return $this
     */
    protected function withFixtureModels()
    {
        config(['cashier.billable_model' => 'Laravel\Cashier\Tests\Fixtures\User']);

        return $this;
    }

    /**
     * Set the system test datetime.
     *
     * @param Carbon|string $now
     * @return $this
     */
    protected function withTestNow($now)
    {
        if (is_string($now)) {
            $now = Carbon::parse($now);
        }
        Carbon::setTestNow($now);

        return $this;
    }

    /**
     * Configure some test plans.
     *
     * @return $this
     */
    protected function withConfiguredPlans()
    {
        config([
            'cashier_plans' => [
                'defaults' => [
                    'first_payment' => [
                        'redirect_url' => 'https://www.example.com',
                        'webhook_url' => 'https://www.example.com/webhooks/mollie/first-payment',
                        'method' => 'ideal',
                        'amount' => [
                            'value' => '0.05',
                            'currency' => 'EUR',
                        ],
                        'description' => 'Test mandate payment',
                    ],
                ],
                'plans' => [
                    'monthly-10-1' => [
                        'amount' => [
                            'currency' => 'EUR',
                            'value' => '10.00',
                        ],
                        'interval' => '1 month',
                        'description' => 'Monthly payment',
                    ],
                    'monthly-10-2' => [
                        'amount' => [
                            'currency' => 'EUR',
                            'value' => '20.00',
                        ],
                        'interval' => '2 months',
                        'method' => 'directdebit',
                        'description' => 'Bimonthly payment',
                    ],
                    'monthly-20-1' => [
                        'amount' => [
                            'currency' => 'EUR',
                            'value' => '20.00',
                        ],
                        'interval' => '1 month',
                        'description' => 'Monthly payment premium',
                    ],
                    'weekly-20-1' => [
                        'amount' => [
                            'currency' => 'EUR',
                            'value' => '20.00',
                        ],
                        'interval' => '1 weeks',
                        'description' => 'Twice as expensive monthly subscription',
                    ],
                ],
            ],
        ]);

        return $this;
    }

    /**
     * Configure some test plans.
     *
     * @return $this
     */
    protected function withConfiguredPlansWithIntervalArray()
    {
        config([
            'cashier_plans' => [
                'defaults' => [
                    'first_payment' => [
                        'redirect_url' => 'https://www.example.com',
                        'webhook_url' => 'https://www.example.com/webhooks/mollie/first-payment',
                        'method' => 'ideal',
                        'amount' => [
                            'value' => '0.05',
                            'currency' => 'EUR',
                        ],
                        'description' => 'Test mandate payment',
                    ],
                ],
                'plans' => [
                    'withixedinterval-10-1' => [
                        'amount' => [
                            'currency' => 'EUR',
                            'value' => '10.00',
                        ],
                        'interval' => [
                            'value' => 1,
                            'period' => 'month',
                            'fixed' => true,
                        ],
                        'description' => 'Monthly payment',
                    ],
                    'withoutfixedinterval-10-1' => [
                        'amount' => [
                            'currency' => 'EUR',
                            'value' => '10.00',
                        ],
                        'interval' => [
                            'value' => 1,
                            'period' => 'month',
                            'fixed' => false,
                        ],
                        'description' => 'Monthly payment',
                    ],
                ],
            ],
        ]);

        return $this;
    }

    protected function getMandatedCustomerId()
    {
        return env('MANDATED_CUSTOMER_DIRECTDEBIT');
    }

    protected function getMandatedCustomer()
    {
        return mollie()->customers()->get($this->getMandatedCustomerId());
    }

    protected function getMandatedUser($persist = true, $overrides = [])
    {
        return $this->getCustomerUser($persist, array_merge([
            'mollie_mandate_id' => 'mdt_unique_mandate_id',
        ], $overrides));
    }

    protected function getCustomerUser($persist = true, $overrides = [])
    {
        return $this->getUser($persist, array_merge([
            'mollie_customer_id' => 'cst_unique_customer_id',
        ], $overrides));
    }

    /**
     * @param bool $persist
     * @param array $overrides
     * @return User
     */
    protected function getUser($persist = true, $overrides = [])
    {
        $user = factory(User::class)->make($overrides);

        if ($persist) {
            $user->save();
        }

        return $user;
    }

    protected function getMandatePayment()
    {
        return mollie()->payments()->get($this->getMandatePaymentID());
    }

    protected function getMandatePaymentID()
    {
        return env('MANDATE_PAYMENT_PAID_ID');
    }

    protected function getSubscriptionMandatePaymentID()
    {
        return env('SUBSCRIPTION_MANDATE_PAYMENT_PAID_ID');
    }

    protected function getMandateId()
    {
        return env('MANDATED_CUSTOMER_DIRECTDEBIT_MANDATE_ID');
    }

    /**
     * @return \Mollie\Api\MollieApiClient
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     * @throws \ReflectionException
     */
    protected function getMollieClientMock()
    {
        return new MollieApiClient($this->createMock(Client::class));
    }

    /**
     * @param int $value
     * @param string $currency
     * @param \Money\Money $money
     */
    protected function assertMoney(int $value, string $currency, Money $money)
    {
        $this->assertEquals($currency, $money->getCurrency()->getCode());
        $this->assertEquals($money->getAmount(), $value);
        $this->assertTrue(money($value, $currency)->equals($money));
    }

    /**
     * @param int $value
     * @param \Money\Money $money
     */
    protected function assertMoneyEURCents(int $value, Money $money)
    {
        $this->assertMoney($value, 'EUR', $money);
    }

    /**
     * @param \Laravel\Cashier\Coupon\Coupon $coupon
     * @param null $couponHandler
     * @param null $context
     * @return CouponRepository The mocked coupon repository
     */
    protected function withMockedCouponRepository(Coupon $coupon = null, $couponHandler = null, $context = null)
    {
        if (is_null($couponHandler)) {
            $couponHandler = new FixedDiscountHandler;
        }

        if (is_null($context)) {
            $context = [
                'description' => 'Test coupon',
                'discount' => [
                    'value' => '5.00',
                    'currency' => 'EUR',
                ],
            ];
        }

        if (is_null($coupon)) {
            $coupon = new Coupon(
                'test-coupon',
                $couponHandler,
                $context
            );
        }

        return $this->mock(CouponRepository::class, function ($mock) use ($coupon) {
            return $mock->shouldReceive('findOrFail')->with($coupon->name())->andReturn($coupon);
        });
    }

    /**
     * Register an instance of an object in the container.
     * Included for Laravel 5.5 / 5.6 compatibility.
     *
     * @param  string  $abstract
     * @param  object  $instance
     * @return object
     */
    protected function instance($abstract, $instance)
    {
        $this->app->instance($abstract, $instance);

        return $instance;
    }

    /**
     * Mock an instance of an object in the container.
     * Included for Laravel 5.5 / 5.6 compatibility.
     *
     * @param  string  $abstract
     * @param  \Closure|null  $mock
     * @return object
     */
    protected function mock($abstract, \Closure $mock = null)
    {
        return $this->instance($abstract, Mockery::mock(...array_filter(func_get_args())));
    }
}
