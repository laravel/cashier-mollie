<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Refunds\Refund;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Refund as MollieRefund;
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

        if ($payment) {
            $order = Order::findByPaymentId($payment->id);

            $this->handlePotentialRefunds($order, $payment);
            $this->handlePotentialChargebacks($order, $payment);
        }

        return new Response(null, 200);
    }

    protected function handlePotentialRefunds(Order $order, Payment $payment)
    {
        if (! $payment->hasRefunds()) {
            return;
        }

        /** @var \Laravel\Cashier\Refunds\RefundCollection $localRefunds */
        $localRefunds = $order->refunds()->whereUnprocessed()->get();
        $mollieRefunds = collect($payment->refunds());

        $localRefunds->each(function (Refund $localRefund) use ($mollieRefunds) {

            /**
             * Get matching Mollie refund.
             * @var MollieRefund $mollieRefund
             */
            $mollieRefund = $mollieRefunds->first(function (MollieRefund $mollieRefund) use ($localRefund) {
                return $mollieRefund->id === $localRefund->mollie_refund_id;
            });

            if ($mollieRefund) {
                if ($mollieRefund->isTransferred()) {
                    $localRefund->handleProcessed();
                } elseif ($mollieRefund->isFailed()) {
                    $localRefund->handleFailed();
                }
            }
        });
    }

    protected function handlePotentialChargebacks(Order $order, Payment $payment)
    {
        if (! $payment->hasChargebacks()) {
            return;
        }

        $orderAmountChargedBack = $order->getAmountChargedBack();
        $currency = $orderAmountChargedBack->getCurrency()->getCode();

        $paymentAmountChargedBack = money(0, $currency);

        /** @var \Mollie\Api\Resources\Chargeback $chargeback */
        foreach ($payment->chargebacks() as $chargeback) {
            $paymentAmountChargedBack->add(mollie_object_to_money($chargeback->amount));
        }

        if ($orderAmountChargedBack->lessThan($paymentAmountChargedBack)) {
            // TODO handle:
            // Subtract from $order->amount_refunded
            // Generate a processed order for the difference, containing an order item detailing the chargeback
            // Dispatch event
        }
    }
}
