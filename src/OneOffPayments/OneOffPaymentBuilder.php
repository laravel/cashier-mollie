<?php

namespace Laravel\Cashier\OneOffPayments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment;
use Laravel\Cashier\Mollie\Contracts\UpdateMolliePayment;
use Laravel\Cashier\Order\OrderItemCollection;
use Laravel\Cashier\Payment;
use Mollie\Api\Types\SequenceType;

class OneOffPaymentBuilder
{
    /**
     * The billable model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * A collection of items that will be added to the Order
     *
     * @var OrderItemCollection
     */
    protected $items;

    /**
     * Overrides the Mollie Payment payload
     *
     * @var array
     */
    protected $options;

    /**
     * The Mollie PaymentMethods you wish to allow
     *
     * @var array<\Mollie\Api\Types\PaymentMethod>
     */
    protected $method;

    /**
     * The payment description.
     *
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $redirectUrl;

    /**
     * @var string
     */
    protected $webhookUrl;

    /**
     * @var \Mollie\Api\Resources\Payment|null
     */
    protected $molliePayment;

    /**
     * OneOffPaymentBuilder constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param array $options Overrides the Mollie Payment payload
     */
    public function __construct(Model $owner, array $options = [])
    {
        $this->owner = $owner;
        $this->items = new TabItemCollection;
        $this->options = $options;
        $this->description = config('app.name', 'One off payment');
        $this->redirectUrl = url(config('cashier.one_off_payment.redirect_url', config('cashier.redirect_url')));
        $this->webhookUrl = url(config('cashier.one_off_payment.webhook_url'));
    }

    public function addItems(TabItemCollection $items): self
    {
        $this->items = $this->items->concat($items);

        return $this;
    }

    /**
     * Build the Mollie Payment Payload
     *
     * @return array
     */
    public function getMolliePayload()
    {
        return array_filter(array_merge([
            'sequenceType' => SequenceType::SEQUENCETYPE_ONEOFF,
            'method' => $this->method,
            'customerId' => $this->owner->asMollieCustomer()->id,
            'locale' => Cashier::getLocale($this->owner),
            'description' => $this->description,
            'amount' => money_to_mollie_array($this->items->total()),
            'webhookUrl' => $this->webhookUrl,
            'redirectUrl' => $this->redirectUrl,
        ], $this->options));
    }

    /**
     * Create a new payment at Mollie for the specified Order Items and redirect the user
     *
     * @return \Mollie\Api\Resources\Payment
     */
    public function create()
    {
        $payload = $this->getMolliePayload();

        /** @var CreateMolliePayment $createMolliePayment */
        $createMolliePayment = app()->make(CreateMolliePayment::class);
        $this->molliePayment = $createMolliePayment->execute($payload);

        Payment::createFromMolliePayment($this->molliePayment, $this->owner);

        $redirectUrl = $payload['redirectUrl'];

        // Parse and update redirectUrl
        if (Str::contains($redirectUrl, '{payment_id}')) {
            $redirectUrl = Str::replaceArray('{payment_id}', [$this->molliePayment->id], $redirectUrl);
            $this->molliePayment->redirectUrl = $redirectUrl;

            /** @var UpdateMolliePayment $updateMolliePayment */
            $updateMolliePayment = app()->make(UpdateMolliePayment::class);
            $this->molliePayment = $updateMolliePayment->execute($this->molliePayment);
        }

        return $this->molliePayment;
    }

    /**
     * @param array $method
     * @return OneOffPaymentBuilder
     */
    public function setPaymentMethods(array $method = [])
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @param string $description
     * @return OneOffPaymentBuilder
     */
    public function setDescription(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Override the default Mollie redirectUrl. Takes an absolute or relative url.
     *
     * @param string $redirectUrl
     * @return $this
     */
    public function setRedirectUrl(string $redirectUrl)
    {
        $this->redirectUrl = url($redirectUrl);

        return $this;
    }

    /**
     * Override the default Mollie webhookUrl. Takes an absolute or relative url.
     *
     * @param string $webhookUrl
     * @return $this
     */
    public function setWebhookUrl(string $webhookUrl)
    {
        $this->webhookUrl = url($webhookUrl);

        return $this;
    }

    /**
     * @return \Mollie\Api\Resources\Payment|null
     */
    public function getMolliePayment()
    {
        return $this->molliePayment;
    }
}
