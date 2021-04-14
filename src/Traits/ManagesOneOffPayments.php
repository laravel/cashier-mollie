<?php


namespace Laravel\Cashier\Traits;

use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\OneOffPayments\OneOffPaymentBuilder;
use Laravel\Cashier\OneOffPayments\Tab;
use Laravel\Cashier\OneOffPayments\TabItemCollection;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse;

trait ManagesOneOffPayments
{
    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param string $description
     * @param int $amount
     * @param array $tabOptions
     * @param array $itemOptions
     * @param array $paymentOptions
     * @return \Laravel\Cashier\Order\Order|bool
     */
    public function chargeFor($description, $amount, array $tabOptions = [],  array $itemOptions = [], array $paymentOptions = [])
    {
        if ($tabOptions['currency'] ?? false) {
            $tabOptions['currency'] = Str::upper($tabOptions['currency']);
            $paymentOptions['currency'] = $tabOptions['currency'];
        }

        $tab = $this->newTab($tabOptions);

        $tab->addItem($description, $amount, $itemOptions);

        $tab->closeNow();

        return $this->invoiceTab($paymentOptions);
    }

    /**
     * Add an open tab to the customer.
     *
     * @param array $overrides
     * @return Tab
     */
    public function newTab($overrides = [])
    {
        // TODO offload to builder

        $defaultOptions = [
            'currency' => Cashier::usesCurrency(),
            'tax' => $this->taxPercentage(),
        ];

        $attributes = array_merge($defaultOptions, $overrides, [
            'owner_type' => $this->getMorphClass(),
            'owner_id' => $this->getKey(),
            'subtotal' => 0,
            'total' => 0,
            'order_id' => null,
        ]);

        $attributes['currency'] = Str::upper($attributes['currency']);

        return Tab::create($attributes);
    }

    /**
     * Invoice the billable entity outside of the regular billing cycle.
     *
     * @param array $paymentOptions
     * @return \Laravel\Cashier\Order\Order|\Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse
     */
    public function invoiceTab(array $paymentOptions = [])
    {
        // TODO move to Tab model

        // Normalize currency; set to default if it's missing, capitalize it.
        if (! ($paymentOptions['currency'] ?? false)) {
            $paymentOptions['currency'] = Cashier::usesCurrency();
        }
        $paymentOptions['currency'] = Str::upper($paymentOptions['currency']);

        // Check if there's something to invoice
        $tabsToOrder = Tab::shouldProcess()
            ->whereOwner($this)
            ->whereCurrency($paymentOptions['currency'])
            ->get();

        // No open order items for this user with the specified currency
        if ($tabsToOrder->isEmpty()) {
            return false;
        }

        if ($this->validateMollieMandate()) {

            //TODO here I am

            return Order::createFromItems($tabsToOrder->items, [
                'currency' => $paymentOptions['currency'],
            ])->processPayment();
        }

        return $this->newOneOffPaymentViaCheckout($tabsToOrder->items, $paymentOptions);
    }

    /**
     * Create a new RedirectToCheckoutResponse for a one off payment.
     *
     * @link https://docs.mollie.com/reference/v2/payments-api/create-payment#parameters
     * @param \Laravel\Cashier\OneOffPayments\TabItemCollection $tabItems
     * @param array $oneOffPaymentOptions !Overrides the Mollie payment options
     * @return RedirectToCheckoutResponse
     */
    protected function newOneOffPaymentViaCheckout(TabItemCollection $tabItems, array $oneOffPaymentOptions = []): RedirectToCheckoutResponse
    {
        // TODO nuke, handle by FirstPaymentBuilder/Handler instead

        // Normalize the payment options. Remove this to prevent 422 from Mollie
        unset($oneOffPaymentOptions['currency']);
        $builder = new OneOffPaymentBuilder($this, $oneOffPaymentOptions);
        dd($tabItems);
        $builder->addItems($tabItems);
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
        // TODO move to Tab

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
