<?php

namespace Laravel\Cashier\Tests\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\OrderPaymentFailed;
use Laravel\Cashier\Events\OrderPaymentPaid;
use Laravel\Cashier\Events\SubscriptionCancelled;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Laravel\Cashier\Mollie\Contracts\GetMolliePayment;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Tests\BaseTestCase;
use Laravel\Cashier\Tests\Fixtures\User;
use Laravel\Cashier\Types\SubscriptionCancellationReason;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;

class WebhookControllerTest extends BaseTestCase
{
    /** @test */
    public function retrievesPaymentResource()
    {
        $id = 'tr_123xyz';

        $this->mock(GetMolliePayment::class, function (GetMolliePayment $mock) use ($id) {
            return $mock->shouldReceive('execute')
                ->with($id)
                ->once()
                ->andReturn(new Payment(new MollieApiClient));
        });

        $this->assertInstanceOf(Payment::class, $this->getController()->getPaymentById($id));
    }

    /** @test **/
    public function MollieApiExceptionIsCatchedWhenDebugDisabled()
    {
        $wrongId = 'sub_xxxxxxxxxxx';
        $this->mock(GetMolliePayment::class, function (GetMolliePayment $mock) use ($wrongId) {
            return $mock->shouldReceive('execute')
                ->with($wrongId)
                ->once()
                ->andThrow(new ApiException);
        });

        $this->assertFalse(config('app.debug'));
        $this->assertNull($this->getController()->getPaymentById($wrongId));
    }

    /** @test **/
    public function MollieApiExceptionIsThrownWhenDebugEnabled()
    {
        $wrongId = 'sub_xxxxxxxxxxx';
        $this->mock(GetMolliePayment::class, function (GetMolliePayment $mock) use ($wrongId) {
            return $mock->shouldReceive('execute')
                ->with($wrongId)
                ->once()
                ->andThrow(new ApiException);
        });

        config(['app.debug' => true]);
        $this->assertTrue(config('app.debug'));
        $this->expectException(ApiException::class);
        $this->assertNull($this->getController()->getPaymentById($wrongId));
    }

    /** @test **/
    public function handlesUnexistingIdGracefully()
    {
        $id = 'tr_xxxxxxxxxxxxx';
        $request = $this->getWebhookRequest($id);

        $this->mock(GetMolliePayment::class, function (GetMolliePayment $mock) use ($id) {
            return $mock->shouldReceive('execute')
                ->with($id)
                ->once()
                ->andThrow(new ApiException);
        });

        $response = $this->getController()->handleWebhook($request);

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

        $paymentId = 'tr_failed_payment_id';

        $order = Order::createFromItems(new OrderItemCollection([$item]), [
            'mollie_payment_id' => $paymentId,
            'mollie_payment_status' => 'open',
            'balance_before' => 500,
            'credit_used' => 500,
        ]);
        $this->assertMoneyEURCents(0, $order->getBalanceAfter());

        $this->assertFalse($user->hasCredit('EUR'));

        $request = $this->getWebhookRequest($paymentId);

        $this->mock(GetMolliePayment::class, function (GetMolliePayment $mock) use ($paymentId) {
            $payment = new Payment(new MollieApiClient);
            $payment->id = $paymentId;
            $payment->status = 'failed';

            return $mock
                ->shouldReceive('execute')
                ->with($paymentId)
                ->once()
                ->andReturn($payment);
        });

        $response = $this->makeTestResponse($this->getController()->handleWebhook($request));

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

        $paymentId = 'tr_payment_paid_id';

        $order->update([
            'mollie_payment_id' => $paymentId,
            'mollie_payment_status' => 'open',
        ]);

        $request = $this->getWebhookRequest($paymentId);

        $this->mock(GetMolliePayment::class, function (GetMolliePayment $mock) use ($paymentId) {
            $payment = new Payment(new MollieApiClient);
            $payment->id = $paymentId;
            $payment->status = 'paid';

            return $mock
                ->shouldReceive('execute')
                ->with($paymentId)
                ->once()
                ->andReturn($payment);
        });

        $response = $this->getController()->handleWebhook($request);

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

        $paymentId = 'tr_payment_paid_id';

        factory(Order::class)->create([
            'mollie_payment_id' => $paymentId,
            'mollie_payment_status' => 'paid',
        ]);

        $request = $this->getWebhookRequest($paymentId);

        $this->mock(GetMolliePayment::class, function (GetMolliePayment $mock) use ($paymentId) {
            $payment = new Payment(new MollieApiClient);
            $payment->id = $paymentId;
            $payment->status = 'paid';

            return $mock
                ->shouldReceive('execute')
                ->with($paymentId)
                ->once()
                ->andReturn($payment);
        });

        $response = $this->getController()->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());
        Event::assertNotDispatched(OrderPaymentPaid::class);
        Event::assertNotDispatched(OrderPaymentFailed::class);
    }

    protected function getController(): WebhookController
    {
        return $this->app->make(WebhookController::class);
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

    protected function makeTestResponse($response)
    {
        if (class_exists('\Illuminate\Foundation\Testing\TestResponse')) {
            // Prior to Laravel v7
            return new \Illuminate\Foundation\Testing\TestResponse($response);
        }

        // Laravel v7
        return new \Illuminate\Testing\TestResponse($response);
    }
}
