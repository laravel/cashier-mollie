<?php


namespace Laravel\Cashier\Traits;

use Dompdf\Options;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\OneOffPayment\OneOffPaymentBuilder;
use Laravel\Cashier\Order\Invoice;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse;

trait ManagesOneOffPayments
{
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
            'is_tab' => true,
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
        return $this->invoiceTab($paymentOptions);
    }

    /**
     * Invoice the billable entity outside of the regular billing cycle.
     *
     * @param array $paymentOptions
     * @return \Laravel\Cashier\Order\Order|\Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse
     */
    public function invoiceTab(array $paymentOptions = [])
    {
        // Normalize currency; set to default if it's missing, capitalize it.
        if (! ($paymentOptions['currency'] ?? false)) {
            $paymentOptions['currency'] = Cashier::usesCurrency();
        }
        $paymentOptions['currency'] = Str::upper($paymentOptions['currency']);

        // Check if there's something to invoice
        $itemsToOrder = OrderItem::shouldProcess()
            ->whereOwner($this)
            ->whereCurrency($paymentOptions['currency'])
            ->isTab()
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
        $builder->setPaymentMethods(config('cashier.one_off_payment.method'));

        return RedirectToCheckoutResponse::forPayment($builder->create());
    }

    /**
     * Get the entity's upcoming invoice in memory. You can inspect it,
     * and if you like what you see you can use the `invoice` method.
     *
     * @param array $overrides
     * @return \Laravel\Cashier\Order\Order|bool
     */
    public function upcomingOrderForTab(array $overrides = [])
    {
        $parameters = array_merge(['currency' => Cashier::usesCurrency()], $overrides);
        $parameters['currency'] = Str::upper($parameters['currency']);

        $items = OrderItem::shouldProcess()
            ->whereOwner($this)
            ->whereCurrency($parameters['currency'])
            ->isTab()
            ->get();

        if ($items->isEmpty()) {
            return false;
        }

        return Order::make($this, $items, $parameters);
    }
}
