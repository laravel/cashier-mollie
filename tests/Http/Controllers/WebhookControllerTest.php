<?php

namespace Laravel\Cashier\Tests\Http\Controllers;

use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\OrderPaymentFailed;
use Laravel\Cashier\Events\OrderPaymentPaid;
use Laravel\Cashier\Events\SubscriptionCancelled;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Laravel\Cashier\Types\SubscriptionCancellationReason;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;

class WebhookControllerTest extends BaseTestCase
{
    private $controller;
    private $payment_paid_id;
    private $payment_failed_id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new WebhookController;
        $this->payment_paid_id = env('PAYMENT_PAID_ID');
        $this->payment_failed_id = env('PAYMENT_FAILED_ID');
        $this->assertNotNull($this->payment_paid_id);
        $this->assertNotNull($this->payment_failed_id);
    }

    /** @test **/
    public function onlyRetrievesPaymentResources()
    {
        $this->assertInstanceOf(Payment::class, $this->controller->getPaymentById($this->payment_paid_id));

        $this->assertFalse(config('app.debug'));
        $this->assertNull($this->controller->getPaymentById('sub_xxxxxxxxxxx'));

        // Assert that in debug mode an ApiException is thrown
        config(['app.debug' => true]);
        $this->expectException(ApiException::class);
        $this->assertNull($this->controller->getPaymentById('sub_xxxxxxxxxxx'));
    }

    /** @test **/
    public function handlesUnexistingIdGracefully()
    {
        $request = $this->getWebhookRequest('tr_xxxxxxxxxxxxx');

        $response = $this->controller->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test **/
    public function handlesPaymentFailed()
    {
        $this->withPackageMigrations();
        $this->withConfiguredPlans();
        $this->withTestNow('2019-01-01');
        Event::fake();

        $user = factory(User::class)->create();
        $subscription = $user->subscriptions()->save(factory(Subscription::class)->make([
            'plan' => 'monthly-10-1',
        ]));
        $item = $subscription->scheduleNewOrderItemAt(now());

        $order = Order::createFromItems(new OrderItemCollection([$item]), [
            'mollie_payment_id' => $this->payment_failed_id,
            'mollie_payment_status' => 'open',
            'balance_before' => 500,
            'credit_used' => 500,
        ]);
        $this->assertMoneyEURCents(0, $order->getBalanceAfter());

        $this->assertFalse($user->hasCredit('EUR'));

        $request = $this->getWebhookRequest($this->payment_failed_id);

        $response = new TestResponse($this->controller->handleWebhook($request));
        $response->assertStatus(200);

        $order = $order->fresh();
        $this->assertEquals('failed', $order->mollie_payment_status);
        $subscription = $subscription->fresh();
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->active());

        // Credits are restored to user balance.
        $this->assertMoneyEURCents(0, $order->getBalanceBefore());
        $this->assertMoneyEURCents(0, $order->getBalanceAfter());
        $this->assertMoneyEURCents(0, $order->getCreditUsed());
        $this->assertMoneyEURCents(500, $user->credit('EUR')->money());

        Event::assertDispatched(OrderPaymentFailed::class, function (OrderPaymentFailed $event) use ($order) {
            return $event->order->is($order);
        });

        Event::assertDispatched(SubscriptionCancelled::class, function (SubscriptionCancelled $event) use ($subscription) {
            $this->assertTrue($event->subscription->is($subscription));
            $this->assertEquals($event->reason, SubscriptionCancellationReason::PAYMENT_FAILED);
            return true;
        });
    }

    /** @test **/
    public function handlesPaidPayment()
    {
        $this->withPackageMigrations();
        $this->withConfiguredPlans();
        Event::fake();

        $user = factory(User::class)->create();
        $subscription = $user->subscriptions()->save(factory(Subscription::class)->make([
            'plan' => 'monthly-10-1',
        ]));
        $item = $subscription->scheduleNewOrderItemAt(now());

        $order = Order::createFromItems(new OrderItemCollection([$item]));
        $order->update([
            'mollie_payment_id' => $this->payment_paid_id,
            'mollie_payment_status' => 'open',
        ]);

        $request = $this->getWebhookRequest($this->payment_paid_id);

        $response = $this->controller->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('paid', $order->fresh()->mollie_payment_status);
        $this->assertTrue($subscription->fresh()->active());

        Event::assertDispatched(OrderPaymentPaid::class, function (OrderPaymentPaid $event) use ($order) {
            return $event->order->is($order);
        });
    }

    /** @test **/
    public function skipsIfPaymentStatusUnchanged()
    {
        $this->withPackageMigrations();
        Event::fake();

        factory(Order::class)->create([
            'mollie_payment_id' => $this->payment_paid_id,
            'mollie_payment_status' => 'paid',
        ]);

        $request = $this->getWebhookRequest($this->payment_paid_id);

        $response = $this->controller->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());
        Event::assertNotDispatched(OrderPaymentPaid::class);
        Event::assertNotDispatched(OrderPaymentFailed::class);
    }

    /**
     * Get a request that mimics Mollie calling the webhook.
     *
     * @param $id
     * @return Request
     */
    protected function getWebhookRequest($id)
    {
        return Request::create('/', 'POST', ['id' => $id]);
    }
}
