<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Cashier\Order\Order;
use Mollie\Api\Resources\Payment;
use Money\Money;
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
        $payment = $this->getPaymentById($request->get('id'));

        if($payment) {
            $order = Order::findByPaymentId($payment->id);

            $this->handlePotentialRefund($order, $payment);
            $this->handlePotentialChargeback($order, $payment);
        }

        return new Response(null, 200);
    }

    protected function handlePotentialRefund(Order $order, Payment $payment)
    {
        $orderAmountRefunded = $order->getAmountRefunded();
        $paymentAmountRefunded = mollie_object_to_money($payment->amountRefunded);
        $difference = $paymentAmountRefunded->subtract($orderAmountRefunded);

        if($difference->isPositive()) {
            // TODO Handle:
            // Subtract from $order->amount_refunded

            // If the refund is not triggered from this app, generate a generic refund, using $billable->taxPercentage()
            // If the refund is triggered from this app (Refund::findByPaymentId(...)), update the Refund

            // Generate a processed order for the difference, containing an order item detailing the refund
            // Dispatch event
        }
    }

    protected function handlePotentialChargeback(Order $order, Payment $payment)
    {
        if(! $payment->hasChargebacks()) {
            return;
        }

        $orderAmountChargedBack = $order->getAmountChargedBack();
        $currency = $orderAmountChargedBack->getCurrency()->getCode();

        $paymentAmountChargedBack = money(0, $currency);

        /** @var \Mollie\Api\Resources\Chargeback $chargeback */
        foreach ($payment->chargebacks() as $chargeback)
        {
            $paymentAmountChargedBack->add(mollie_object_to_money($chargeback->amount));
        }

        if($orderAmountChargedBack->lessThan($paymentAmountChargedBack)) {
            // TODO handle:
            // Subtract from $order->amount_refunded
            // Generate a processed order for the difference, containing an order item detailing the chargeback
            // Dispatch event
        }
    }
}
