<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Refunds\Refund;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Refund as MollieRefund;
use Mollie\Api\Types\RefundStatus;
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

        if ($payment && $payment->hasRefunds()) {
            $order = Order::findByMolliePaymentId($payment->id);

            $this->handleRefunds($order, $payment);
        }

        return new Response(null, 200);
    }

    protected function handleRefunds(Order $order, Payment $payment)
    {
        /** @var \Laravel\Cashier\Refunds\RefundCollection $localRefunds */
        $localRefunds = $order->refunds()->whereUnprocessed()->get();
        $mollieRefunds = collect($payment->refunds());

        $localRefunds->each(function (Refund $localRefund) use ($mollieRefunds) {
            $mollieRefund = $this->matchingMollieRefundForLocalRefund($localRefund, $mollieRefunds);

            if ($mollieRefund) {
                if ($mollieRefund->isTransferred() && $localRefund->mollie_refund_status !== RefundStatus::STATUS_REFUNDED) {
                    $localRefund->handleProcessed();
                } elseif ($mollieRefund->isFailed() && $localRefund->mollie_refund_status !== RefundStatus::STATUS_FAILED) {
                    $localRefund->handleFailed();
                }
            }
        });
    }

    /**
     * @param \Laravel\Cashier\Refunds\Refund $localRefund
     * @param \Illuminate\Support\Collection $mollieRefunds
     * @return MollieRefund|null
     */
    protected function matchingMollieRefundForLocalRefund(Refund $localRefund, Collection $mollieRefunds)
    {
        return $mollieRefunds->first(function (MollieRefund $mollieRefund) use ($localRefund) {
            return $mollieRefund->id === $localRefund->mollie_refund_id;
        });
    }
}
