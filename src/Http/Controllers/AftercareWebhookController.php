<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Cashier\Order\Order;
use Mollie\Api\Resources\Payment;
use Symfony\Component\HttpFoundation\Response;

class AftercareWebhookController extends BaseWebhookController
{
    /**
     * @param Request $request
     * @return Response
     * @throws \Mollie\Api\Exceptions\ApiException Only in debug mode
     */
    public function handleWebhook(Request $request)
    {
        $payment = $this->getPaymentById($request->get('id'), [

        ]);

        if($payment) {
            $order = Order::findByPaymentId($payment->id);

            $this->handlePotentialRefund($order, $payment);
            $this->handlePotentialChargeback($order, $payment);
        }

        return new Response(null, 200);
    }

    protected function handlePotentialRefund(Order $order, Payment $payment)
    {
        $paymentAmountRefunded = mollie_object_to_money($payment->amountRefunded);
        $orderAmountRefunded = $order->getAmountRefunded();

        if($orderAmountRefunded->lessThan($paymentAmountRefunded)) {
            // TODO Handle
        }
    }

    protected function handlePotentialChargeback(Order $order, Payment $payment)
    {
        //$paymentAmountChargedBack = mollie_object_to_money($payment->char)
    }
}
