<?php

namespace Laravel\Cashier\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Cashier\Order\ConvertsToMoney;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Traits\HasOwner;
use Mollie\Api\Resources\Payment as MolliePayment;
use Money\Money;

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
     * @param array $models
     * @return \Laravel\Cashier\Payments\PaymentCollection
     */
    public function newCollection(array $models = []): PaymentCollection
    {
        return new PaymentCollection($models);
    }

    public static function createFromMolliePayment(MolliePayment $payment, Model $owner, array $overrides = []): self
    {
        return tap(static::makeFromMolliePayment($payment, $owner, $overrides))->save();
    }

    public static function makeFromMolliePayment(MolliePayment $payment, Model $owner, array $overrides = []): self
    {
        $chargebackAmount = money(0, $payment->amount->currency);
        if ($payment->hasChargebacks()) {
            foreach ($payment->chargebacks() as $chargeback) {
                $chargebackAmount->add(mollie_object_to_money($chargeback->amount));
            }
        }

        return static::make(array_merge([
            'mollie_payment_id' => $payment->id,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->{$owner->getForeignKey()},
            'status' => $payment->status,
            'currency' => $payment->amount->currency,
            'amount' => $payment->amount->value,
            'amount_refunded' => $payment->amountRefunded->value,
            'amount_charged_back' => (int) $chargebackAmount->getAmount(),
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

    /**
     * Get Orders for this payment. One Payment can relate to multiple Orders, as a refund and chargeback result in
     * in additional Order records. A first Payment has no order until it is marked as paid.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class);
    }
}
