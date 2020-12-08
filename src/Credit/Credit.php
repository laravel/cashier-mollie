<?php

namespace Laravel\Cashier\Credit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Traits\HasOwner;
use Money\Money;

class Credit extends Model
{
    use HasOwner;

    protected $guarded = [];

    /**
     * Add a credit amount for a specific owner.
     *
     * @param Model $owner
     * @param \Money\Money $amount
     * @return Model|\Laravel\Cashier\Credit\Credit
     */
    public static function addAmountForOwner(Model $owner, Money $amount)
    {
        return DB::transaction(function () use ($owner, $amount) {
            $current = static::whereOwner($owner)->whereCurrency($amount->getCurrency()->getCode())->first();

            // if the owner already has a credit
            if ($current) {
                $current->increment('value', (int) $amount->getAmount());

                return $current;
            }

            // if the owner has no credit yet
            return static::create([
                'owner_id' => $owner->id,
                'owner_type' => get_class($owner),
                'currency' => $amount->getCurrency()->getCode(),
                'value' => (int) $amount->getAmount(),
            ]);
        });
    }

    /**
     * Use the max amount of owner's credit balance, striving to credit the target amount provided.
     *
     * @param Model $owner
     * @param \Money\Money $amount The target amount
     * @return \Money\Money The amount that was credited to the owner's balance.
     */
    public static function maxOutForOwner(Model $owner, Money $amount)
    {
        return DB::transaction(function () use ($owner, $amount) {
            $credit = static::whereOwner($owner)->whereCurrency($amount->getCurrency()->getCode())->firstOrCreate([]);

            if ($credit->value == 0) {
                return money(0, $amount->getCurrency()->getCode());
            }

            $use_credit = min([$credit->value, (int) $amount->getAmount()]);
            $credit->decrement('value', $use_credit);

            return money($use_credit, $amount->getCurrency()->getCode());
        });
    }

    /**
     * Get the amount of credit as Money object.
     *
     * @return \Money\Money
     */
    public function money()
    {
        return money($this->value, $this->currency);
    }
}
