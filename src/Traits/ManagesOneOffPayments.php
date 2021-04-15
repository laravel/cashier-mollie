<?php


namespace Laravel\Cashier\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\OneOffPayments\OneOffPaymentBuilder;
use Laravel\Cashier\OneOffPayments\Tab;
use Laravel\Cashier\OneOffPayments\TabBuilder;
use Laravel\Cashier\OneOffPayments\TabItemCollection;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse;

trait ManagesOneOffPayments
{
    public function tabs(): MorphMany
    {
        return $this->morphMany(Tab::class, 'owner');
    }

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
     * Prepare a tab for the customer.
     *
     * @return \Laravel\Cashier\OneOffPayments\TabBuilder
     */
    public function newTab()
    {
        return (new TabBuilder($this, Cashier::usesCurrency()))
            ->withDefaultTaxPercentage($this->taxPercentage());
    }

    /**
     * Invoice the billable entity outside of the regular billing cycle.
     *
     * @param array $paymentOptions
     * @return \Laravel\Cashier\Order\Order|\Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse
     * @throws \Laravel\Cashier\Exceptions\InvalidMandateException
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
     * Get the entity's upcoming invoice in memory. You can inspect it,
     * and if you like what you see you can use the `invoice` method.
     *
     * @param array $overrides
     * @return \Laravel\Cashier\Order\Order|bool
     */
    public function upcomingOrderForTab(array $overrides = [])
    {
        // TODO move to Tab? Not sure yet

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
