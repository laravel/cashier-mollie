<?php

declare(strict_types=1);

namespace Laravel\Cashier\Plan;

use Laravel\Cashier\Order\OrderItemPreprocessorCollection;
use Laravel\Cashier\Plan\Contracts\Plan as PlanContract;
use Money\Money;

class Plan implements PlanContract
{
    /**
     * A unique reference for this plan.
     *
     * @var string
     */
    protected $name;

    /**
     * The amount of the payment.
     *
     * @var \Money\Money
     */
    protected $amount;

    /**
     * The billing frequency.
     *
     * @var string
     * @example 1 month
     */
    protected $interval;

    /**
     * A user friendly description to be included in the invoice.
     * @var string
     * @example A dummy example subscription
     */
    protected $description;

    /**
     * The amount used for a mandate payment.
     *
     * @var \Money\Money
     */
    protected $firstPaymentAmount;

    /**
     * The first payment method
     *
     * @var string
     * @example ideal
     */
    protected $firstPaymentMethod;

    /**
     * The description for the mandate payment order item.
     *
     * @var string
     */
    protected $firstPaymentDescription;

    /**
     * The url the customer should be redirected to after completing the Mollie checkout.
     *
     * @var string
     */
    protected $firstPaymentRedirectUrl;

    /**
     * The url Mollie calls on a status update.
     *
     * @var string
     */
    protected $firstPaymentWebhookUrl;

    /** @var \Laravel\Cashier\Order\OrderItemPreprocessorCollection */
    protected $orderItemPreprocessors;

    /**
     * Plan constructor.
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
        $this->orderItemPreprocessors = new OrderItemPreprocessorCollection;
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return \Money\Money
     */
    public function amount()
    {
        return $this->amount;
    }

    /**
     * @param \Money\Money $amount
     * @return $this
     */
    public function setAmount(Money $amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return string
     */
    public function description()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string
     */
    public function firstPaymentMethod()
    {
        return $this->firstPaymentMethod;
    }

    /**
     * @param string $firstPaymentMethod
     * @return $this
     */
    public function setFirstPaymentMethod(?string $firstPaymentMethod)
    {
        $this->firstPaymentMethod = $firstPaymentMethod;

        return $this;
    }

    /**
     * The amount the customer is charged for a mandate payment.
     *
     * @return \Money\Money
     */
    public function firstPaymentAmount()
    {
        return $this->firstPaymentAmount;
    }

    /**
     * @param \Money\Money $firstPaymentAmount
     * @return $this
     */
    public function setFirstPaymentAmount(Money $firstPaymentAmount)
    {
        $this->firstPaymentAmount = $firstPaymentAmount;

        return $this;
    }

    /**
     * The description for the mandate payment order item.
     *
     * @return string
     */
    public function firstPaymentDescription()
    {
        return $this->firstPaymentDescription;
    }

    /**
     * @param string $firstPaymentDescription
     * @return $this
     */
    public function setFirstPaymentDescription(string $firstPaymentDescription)
    {
        $this->firstPaymentDescription = $firstPaymentDescription;

        return $this;
    }

    /**
     * @return string
     */
    public function interval()
    {
        return $this->interval;
    }

    /**
     * @param string $interval
     * @return $this
     */
    public function setInterval(string $interval)
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * @return string
     */
    public function firstPaymentRedirectUrl()
    {
        return $this->firstPaymentRedirectUrl;
    }

    /**
     * @param string $redirectUrl
     * @return $this
     */
    public function setFirstPaymentRedirectUrl(string $redirectUrl)
    {
        $this->firstPaymentRedirectUrl = $redirectUrl;

        return $this;
    }

    /**
     * @return string
     */
    public function firstPaymentWebhookUrl()
    {
        return $this->firstPaymentWebhookUrl;
    }

    /**
     * @param string $webhookUrl
     * @return PlanContract
     */
    public function setFirstPaymentWebhookUrl(string $webhookUrl)
    {
        $this->firstPaymentWebhookUrl = $webhookUrl;

        return $this;
    }

    /**
     * @return \Laravel\Cashier\Order\OrderItemPreprocessorCollection
     */
    public function orderItemPreprocessors()
    {
        return $this->orderItemPreprocessors;
    }

    /**
     * @param \Laravel\Cashier\Order\OrderItemPreprocessorCollection $preprocessors
     * @return \Laravel\Cashier\Plan\Contracts\Plan
     */
    public function setOrderItemPreprocessors(OrderItemPreprocessorCollection $preprocessors)
    {
        $this->orderItemPreprocessors = $preprocessors;

        return $this;
    }
}
