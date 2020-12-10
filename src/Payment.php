<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Order\ConvertsToMoney;
use Laravel\Cashier\Traits\HasOwner;
use Mollie\Api\Resources\Payment as MolliePayment;
use Money\Money;

/**
 * @property string mollie_payment_id
 * @property string mollie_payment_status
 * @property string owner_type
 * @property int owner_id
 * @property Model owner
 * @property int order_id
 * @property string status
 * @property string currency
 * @property int amount
 * @property int amount_refunded
 * @property int amount_charged_back
 * @property string actions
 * @method static create(array $data)
 * @method static make(array $data)
 */
class Payment extends Model
{
    use ConvertsToMoney;
    use HasOwner;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * @param \Mollie\Api\Resources\Payment $payment
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param array $overrides
     * @return static
     */
    public static function createFromMolliePayment(MolliePayment $payment, Model $owner, array $overrides = []): self
    {
        return tap(static::makeFromMolliePayment($payment, $owner, $overrides))->save();
    }

    /**
     * @param \Mollie\Api\Resources\Payment $payment
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param array $overrides
     * @return static
     */
    public static function makeFromMolliePayment(MolliePayment $payment, Model $owner, array $overrides = []): self
    {
        $amountChargedBack = $payment->amountChargedBack
            ? mollie_object_to_money($payment->amountChargedBack)
            : money(0, $payment->amount->currency);

        $amountRefunded = $payment->amountRefunded
            ? mollie_object_to_money($payment->amountRefunded)
            : money(0, $payment->amount->currency);

        return static::make(array_merge([
            'mollie_payment_id' => $payment->id,
            'mollie_payment_status' => $payment->status,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->id,
            'status' => $payment->status,
            'currency' => $payment->amount->currency,
            'amount' => (int) mollie_object_to_money($payment->amount)->getAmount(),
            'amount_refunded' => (int) $amountRefunded->getAmount(),
            'amount_charged_back' => (int) $amountChargedBack->getAmount(),
        ], $overrides));
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @return \Money\Money
     */
    public function getAmount(): Money
    {
        return $this->toMoney($this->amount);
    }

    /**
     * @return \Money\Money
     */
    public function getAmountRefunded(): Money
    {
        return $this->toMoney($this->amount_refunded);
    }

    /**
     * @return \Money\Money
     */
    public function getAmountChargedBack(): Money
    {
        return $this->toMoney($this->amount_charged_back);
    }
}
