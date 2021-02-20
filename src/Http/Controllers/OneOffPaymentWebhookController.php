<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\OrderPaymentFailed;
use Laravel\Cashier\Events\OrderPaymentPaid;
use Laravel\Cashier\OneOffPayment\OneOffPaymentHandler;
use Symfony\Component\HttpFoundation\Response;

class OneOffPaymentWebhookController extends BaseWebhookController
{
    /**
     * @param Request $request
     * @return Response
     * @throws \Mollie\Api\Exceptions\ApiException Only in debug mode
     */
    public function handleWebhook(Request $request)
    {
        $payment = $this->getMolliePaymentById($request->get('id'));

        if ($payment) {
            if ($payment->isPaid()) {
                $order = (new OneOffPaymentHandler($payment))->execute();
                $payment->webhookUrl = route('webhooks.mollie.aftercare');
                $payment->update();

                Event::dispatch(new OrderPaymentPaid($order));
            } elseif ($payment->isFailed()) {
                Event::dispatch(new OrderPaymentFailed($payment));
            }
        }

        return new Response(null, 200);
    }
}
