<?php


namespace Laravel\Cashier\Traits;

use Dompdf\Options;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Exceptions\UnauthorizedInvoiceAccessException;
use Laravel\Cashier\OneOffPayment\OneOffPaymentBuilder;
use Laravel\Cashier\Order\Invoice;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ManagesInvoices
{
    /**
     * Find an invoice by ID.
     *
     * @param string $orderId
     * @return Invoice|null
     * @throws \Laravel\Cashier\Exceptions\UnauthorizedInvoiceAccessException
     */
    public function findInvoice($orderId)
    {
        /** @var Order|null $order */
        $order = Order::find($orderId);

        if (is_null($order)) {
            return null;
        }

        if ($order->owner->isNot($this)) {
            throw new UnauthorizedInvoiceAccessException;
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
        try {
            $invoice = $this->findInvoice($id);
        } catch (UnauthorizedInvoiceAccessException $exception) {
            throw new AccessDeniedHttpException;
        }

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

    /**
     * Add an invoice item to the customer's upcoming invoice.
     *
     * @param string $description
     * @param int $amount
     * @param array $overrides
     * @return OrderItem
     */
    public function tab($description, $amount, array $overrides = [])
    {
        $defaultOptions = [
            'currency' => Cashier::usesCurrency(),
            'unit_price' => $amount,
            'tax_percentage' => $this->taxPercentage(),
            'description' => $description,
            'process_at' => now()->subMinute(), // Next time Cashier runs, or when using `invoice` method.
        ];

        $attributes = array_merge($defaultOptions, $overrides, [
            'owner_type' => $this->getMorphClass(),
            'owner_id' => $this->getKey(),
            'order_id' => null,
        ]);

        $attributes['currency'] = Str::upper($attributes['currency']);

        return OrderItem::create($attributes);
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param string $description
     * @param int $amount
     * @param array $tabOptions
     * @param array $paymentOptions
     * @return \Laravel\Cashier\Order\Order|bool
     */
    public function invoiceFor($description, $amount, array $tabOptions = [], $paymentOptions = [])
    {
        if ($tabOptions['currency'] ?? false) {
            $tabOptions['currency'] = Str::upper($tabOptions['currency']);
            $paymentOptions['currency'] = $tabOptions['currency'];
        }

        $this->tab($description, $amount, $tabOptions);

        // Force the invoice method to use the same currency as the order item.
        return $this->invoice($paymentOptions);
    }

    /**
     * Invoice the billable entity outside of the regular billing cycle.
     *
     * @param array $paymentOptions
     * @return \Laravel\Cashier\Order\Order|\Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse
     */
    public function invoice(array $paymentOptions = [])
    {
        // Normalize currency; set to default if it's missing, capitalize it.
        if (! ($paymentOptions['currency'] ?? false)) {
            $paymentOptions['currency'] = Cashier::usesCurrency();
        }
        $paymentOptions['currency'] = Str::upper($paymentOptions['currency']);

        // Check if there's something to invoice
        $itemsToOrder = OrderItem::shouldProcess()
            ->forOwner($this)
            ->ofCurrency($paymentOptions['currency'])
            ->get();

        // No open order items for this user with the specified currency
        if ($itemsToOrder->isEmpty()) {
            return false;
        }

        if ($this->validateMollieMandate()) {
            return Order::createFromItems($itemsToOrder, [
                'currency' => $paymentOptions['currency'],
            ])->processPayment();
        }

        return $this->newOneOffPaymentViaCheckout($itemsToOrder, $paymentOptions);
    }

    /**
     * Create a new RedirectToCheckoutResponse for a one off payment.
     *
     * @link https://docs.mollie.com/reference/v2/payments-api/create-payment#parameters
     * @param OrderItemCollection $items
     * @param array $oneOffPaymentOptions !Overrides the Mollie payment options
     * @return RedirectToCheckoutResponse
     */
    protected function newOneOffPaymentViaCheckout(OrderItemCollection $items, array $oneOffPaymentOptions = [])
    {
        // Normalize the payment options. Remove this to prevent 422 from Mollie
        unset($oneOffPaymentOptions['currency']);
        $builder = new OneOffPaymentBuilder($this, $oneOffPaymentOptions);

        $builder->forItems($items);
        $builder->setRedirectUrl(config('cashier.one_off_payment.redirect_url'));
        $builder->setWebhookUrl(config('cashier.one_off_payment.webhook_url'));
        $builder->setDescription(config('cashier.one_off_payment.description'));
        $builder->setPaymentMethods(config('cashier.one_off_payment.active_payment_methods'));

        return RedirectToCheckoutResponse::forPayment($builder->create());
    }

    /**
     * Get the entity's upcoming invoice in memory. You can inspect it,
     * and if you like what you see you can use the `invoice` method.
     *
     * @param array $overrides
     * @return \Laravel\Cashier\Order\Order|bool
     */
    public function upcomingInvoice(array $overrides = [])
    {
        $parameters = array_merge(['currency' => Cashier::usesCurrency()], $overrides);
        $parameters['currency'] = Str::upper($parameters['currency']);

        $items = OrderItem::shouldProcess()
            ->forOwner($this)
            ->ofCurrency($parameters['currency'])
            ->get();

        if ($items->isEmpty()) {
            return false;
        }

        return Order::make($this, $items, $parameters);
    }
}
