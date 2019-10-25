<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Order\Order;
use Mollie\Api\Types\PaymentStatus;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends BaseWebhookController
{
    /**
     * @param Request $request
     * @return Response
     * @throws \Mollie\Api\Exceptions\ApiException Only in debug mode
     */
    public function handleWebhook(Request $request)
    {
        $payment = $this->getPaymentById($request->get('id'));

        if($payment) {
            $order = Order::findByPaymentId($payment->id);
            if ($order && $order->mollie_payment_status !== $payment->status) {
                switch ($payment->status) {
                    case PaymentStatus::STATUS_PAID:
                        $order->handlePaymentPaid();
                        break;
                    case PaymentStatus::STATUS_FAILED:
                        $order->handlePaymentFailed();
                        break;
                    default:
                        break;
                }
            }
        }

        return new Response(null, 200);
    }
}
