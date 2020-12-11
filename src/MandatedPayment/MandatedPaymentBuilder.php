<?php

namespace Laravel\Cashier\MandatedPayment;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Mollie\Contracts\CreateMolliePayment;
use Mollie\Api\Types\SequenceType;
use Money\Money;

class MandatedPaymentBuilder
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var \Money\Money
     */
    protected $amount;

    /**
     * @var string
     */
    protected $webhookUrl;

    /**
     * @var array
     */
    protected $overrides;

    /**
     * MandatedPaymentBuilder constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param string $description
     * @param \Money\Money $amount
     * @param string $webhookUrl
     * @param array $overrides
     */
    public function __construct(
        Model $owner,
        string $description,
        Money $amount,
        string $webhookUrl,
        array $overrides = []
    ) {
        $this->owner = $owner;
        $this->description = $description;
        $this->amount = $amount;
        $this->webhookUrl = $webhookUrl;
        $this->overrides = $overrides;
    }

    /**
     * @param array $overrides
     * @return array
     */
    public function getPayload(array $overrides = [])
    {
        $overrides = array_merge($this->overrides, $overrides);

        return array_filter(array_merge([
            'sequenceType' => SequenceType::SEQUENCETYPE_RECURRING,
            'mandateId' => $this->owner->mollieMandateId(),
            'customerId' => $this->owner->mollieCustomerId(),
            'locale' => Cashier::getLocale($this->owner),
            'amount' => money_to_mollie_array($this->amount),
            'description' => $this->description,
            'webhookUrl' => $this->webhookUrl,
        ], $overrides));
    }

    /**
     * @param array $overrides
     * @return \Mollie\Api\Resources\Payment
     */
    public function create(array $overrides = [])
    {
        /** @var CreateMolliePayment $createMolliePayment */
        $createMolliePayment = app()->make(CreateMolliePayment::class);

        return $createMolliePayment->execute($this->getPayload($overrides));
    }
}
