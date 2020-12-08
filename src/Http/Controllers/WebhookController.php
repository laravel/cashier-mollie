<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Cashier\Order\Order;
use Mollie\Api\Resources\Payment;
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

        if ($payment) {
            $order = $this->getOrder($payment);

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

    /**
     * @param \Mollie\Api\Resources\Payment $payment
     * @return \Laravel\Cashier\Order\Order|null
     */
    protected function getOrder(Payment $payment)
    {
        $order = Order::findByPaymentId($payment->id);

        if (! $order && isset($payment->metadata, $payment->metadata->temporary_mollie_payment_id)) {
            $order = Order::findByPaymentId($payment->metadata->temporary_mollie_payment_id);

            if ($order) {
                // Store the definite payment id.
                $order->update(['mollie_payment_id' => $payment->id]);
            }
        }

        return $order;
    }
}
