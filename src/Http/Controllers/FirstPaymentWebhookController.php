<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\FirstPaymentFailed;
use Laravel\Cashier\Events\FirstPaymentPaid;
use Laravel\Cashier\FirstPayment\FirstPaymentHandler;
use Symfony\Component\HttpFoundation\Response;

class FirstPaymentWebhookController extends BaseWebhookController
{
    /**
     * @param Request $request
     * @return Response
     * @throws \Mollie\Api\Exceptions\ApiException Only in debug mode
     */
    public function handleWebhook(Request $request)
    {
        $payment = $this->getPaymentById($request->get('id'));

        if ($payment) {
            if ($payment->isPaid()) {
                $order = (new FirstPaymentHandler($payment))->execute();

                Event::dispatch(new FirstPaymentPaid($payment, $order));
            } elseif ($payment->isFailed()) {
                Event::dispatch(new FirstPaymentFailed($payment));
            }
        }

        return new Response(null, 200);
    }
}
