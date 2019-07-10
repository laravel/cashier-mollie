<?php

namespace Laravel\Cashier\SubscriptionBuilder;

use Illuminate\Http\RedirectResponse;
use Mollie\Api\Resources\Payment;

class RedirectToCheckoutResponse extends RedirectResponse
{
    /** @var array */
    protected $context = [];

    /** @var \Mollie\Api\Resources\Payment */
    protected $payment;

    /**
     * @var \Laravel\Cashier\SubscriptionBuilder\FirstPaymentSubscriptionBuilder
     */
    protected $firstPaymentSubscriptionBuilder;

    /**
     * @param \Mollie\Api\Resources\Payment $payment
     * @param array $context
     * @return \Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse
     */
    public static function forPayment(Payment $payment, array $context = [])
    {
        $response = new static($payment->getCheckoutUrl());

        return $response
            ->setPayment($payment)
            ->setContext($context);
    }

    /**
     * @param \Laravel\Cashier\SubscriptionBuilder\FirstPaymentSubscriptionBuilder $builder
     * @param array $context
     * @return \Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse
     */
    public static function forFirstPaymentSubscriptionBuilder(FirstPaymentSubscriptionBuilder $builder, array $context = [])
    {
        $payment = $builder->getMandatePaymentBuilder()->getMolliePayment();

        return (new static($payment->getCheckoutUrl()))
            ->setBuilder($builder)
            ->setPayment($payment)
            ->setContext($context);
    }

    /**
     * @return \Mollie\Api\Resources\Payment
     */
    public function payment()
    {
        return $this->payment;
    }

    /**
     * @return array
     */
    public function context()
    {
        return $this->context;
    }

    /**
     * @param \Mollie\Api\Resources\Payment $payment
     * @return \Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse
     */
    protected function setPayment(Payment $payment)
    {
        $this->payment = $payment;

        return $this;
    }

    /**
     * @param array $context
     * @return $this
     */
    public function setContext(array $context)
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @param \Laravel\Cashier\SubscriptionBuilder\FirstPaymentSubscriptionBuilder $builder
     * @return $this
     */
    protected function setBuilder(FirstPaymentSubscriptionBuilder $builder)
    {
        $this->firstPaymentSubscriptionBuilder = $builder;

        return $this;
    }
}
