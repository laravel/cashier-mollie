<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\FirstPaymentFailed;
use Laravel\Cashier\Events\FirstPaymentPaid;
use Laravel\Cashier\FirstPayment\FirstPaymentHandler;
use Laravel\Cashier\Order\Order;
use Mollie\Api\Types\PaymentStatus;
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
        $paymentId = $request->get('id');

        // If a paid order already exists this webhook call is for a refund or chargeback
        $existingOrder = Order::findByPaymentId($paymentId);
        if ($existingOrder && $existingOrder->mollie_payment_status === PaymentStatus::STATUS_PAID) {

            // Do nothing. Refunds and chargebacks are not supported in cashier-mollie v1
            return new Response(null, 200);
        }

        $payment = $this->getPaymentById($paymentId);

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
