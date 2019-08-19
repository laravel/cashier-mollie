<?php

namespace Laravel\Cashier\FirstPayment;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\FirstPayment\Actions\ActionCollection;
use Mollie\Api\Types\SequenceType;

class FirstPaymentBuilder
{
    /**
     * The billable model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * A collection of BaseAction items. These actions will be executed by the FirstPaymentHandler
     *
     * @var ActionCollection
     */
    protected $actions;

    /**
     * Overrides the Mollie Payment payload
     *
     * @var array
     */
    protected $options;

    /**
     * The Mollie PaymentMethod
     *
     * @var string
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
     * @var \Mollie\Api\Resources\Payment|null
     */
    protected $molliePayment;

    /**
     * FirstPaymentBuilder constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param array $options Overrides the Mollie Payment payload
     */
    public function __construct(Model $owner, array $options = [])
    {
        $this->owner = $owner;
        $this->actions = new ActionCollection;
        $this->options = $options;
        $this->description = config('app.name', 'First payment');
        $this->redirectUrl = url(config('cashier.first_payment.redirect_url', config('cashier.redirect_url')));
    }

    /**
     * Define actions to be executed once the payment has been paid.
     *
     * @param array $actions
     * @return $this
     */
    public function inOrderTo(array $actions = [])
    {
        $this->actions = new ActionCollection($actions);

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
            'sequenceType' => SequenceType::SEQUENCETYPE_FIRST,
            'method' => $this->method,
            'customerId' => $this->owner->asMollieCustomer()->id,
            'locale' => Cashier::getLocale($this->owner),
            'description' => $this->description,
            'amount' => money_to_mollie_array($this->actions->total()),
            'webhookUrl' => url(config('cashier.first_payment.webhook_url')),
            'redirectUrl' => $this->redirectUrl,
            'metadata' => [
                'owner' => [
                    'type' => get_class($this->owner),
                    'id' => $this->owner->id,
                ],
                'actions' => $this->actions->toMolliePayload(),
            ],
        ], $this->options));
    }

    /**
     * @return \Mollie\Api\Resources\Payment
     */
    public function create()
    {
        $this->molliePayment = mollie()->payments()->create($this->getMolliePayload());

        return $this->molliePayment;
    }

    /**
     * @param string $method
     * @return FirstPaymentBuilder
     */
    public function setFirstPaymentMethod(?string $method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @param string $description
     * @return FirstPaymentBuilder
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
     * @return \Mollie\Api\Resources\Payment|null
     */
    public function getMolliePayment()
    {
        return $this->molliePayment;
    }
}
