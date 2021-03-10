<?php


namespace Laravel\Cashier\Traits;

use Dompdf\Options;
use Laravel\Cashier\Order\Invoice;
use Laravel\Cashier\Order\Order;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ManagesInvoices
{
    /**
     * Find an invoice by ID.
     *
     * @param string $orderId
     * @return Invoice|null
     */
    public function findInvoice($orderId)
    {
        /** @var Order|null $order */
        $order = Order::find($orderId);

        if (is_null($order)) {
            return null;
        }

        return $order->invoice();
    }

    /**
     * Find an invoice or throw a 404 or 403 error.
     *
     * @param string $id
     * @return \Laravel\Cashier\Order\Invoice
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function findInvoiceOrFail($id)
    {
        $invoice = $this->findInvoice($id);

        if (is_null($invoice)) {
            throw new NotFoundHttpException;
        }

        return $invoice;
    }

    /**
     * Get the invoice instances for this model.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function invoices()
    {
        return $this->orders->invoices();
    }

    /**
     * Create an invoice download response.
     *
     * @param $orderId
     * @param array $data
     * @param string $view
     * @param \Dompdf\Options|null $options
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice($orderId, $data = [], $view = Invoice::DEFAULT_VIEW,  Options $options = null)
    {
        /** @var Order $order */
        $order = $this->orders()->where('id', $orderId)->firstOrFail();

        return $order->invoice()->download($data, $view, $options);
    }
}
