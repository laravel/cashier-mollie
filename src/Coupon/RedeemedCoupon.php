<?php

namespace Laravel\Cashier\Coupon;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Coupon\Contracts\AcceptsCoupons;
use Laravel\Cashier\Coupon\Contracts\CouponRepository;
use Laravel\Cashier\Order\OrderItemCollection;

/**
 * @method static create(array $array)
 * @method static whereModel($modelType, $modelId)
 * @property mixed id
 * @property string model_type
 * @property mixed model_id
 * @property string name
 * @property int times_left
 */
class RedeemedCoupon extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * @param \Laravel\Cashier\Coupon\Coupon $coupon
     * @param \Laravel\Cashier\Coupon\Contracts\AcceptsCoupons $model
     * @return \Illuminate\Database\Eloquent\Model|\Laravel\Cashier\Coupon\RedeemedCoupon
     */
    public static function record(Coupon $coupon, AcceptsCoupons $model)
    {
        return $model->redeemedCoupons()->create([
            'name' => $coupon->name(),
            'times_left' => $coupon->times(),
            'owner_type' => $model->ownerType(),
            'owner_id' => $model->ownerId(),
        ]);
    }

    /**
     * Retrieve the underlying Coupon object.
     *
     * @return Coupon
     */
    public function coupon()
    {
        /** @var CouponRepository $repository */
        $repository = app()->make(CouponRepository::class);

        return $repository->findOrFail($this->name);
    }

    /**
     * @return \Laravel\Cashier\Coupon\Contracts\CouponHandler
     */
    public function handler()
    {
        return $this->coupon()->handler();
    }

    /**
     * Get the model relation the coupon was redeemed for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function model()
    {
        return $this->morphTo();
    }

    /**
     * @param \Laravel\Cashier\Order\OrderItemCollection $items
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function applyTo(OrderItemCollection $items)
    {
        return $this->coupon()->applyTo($this, $items);
    }

    /**
     * @return $this
     */
    public function markApplied()
    {
        $this->decrement('times_left');

        return $this;
    }

    /**
     * @return $this
     */
    public function markRollback()
    {
        $this->increment('times_left');

        return $this;
    }

    /**
     * Scope a query to only include coupons which are being processed
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive(Builder $query)
    {
        return $query->where('times_left', '>', 0);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $modelType
     * @param $modelId
     * @return mixed
     */
    public function scopeWhereModel(Builder $query, string $modelType, $modelId)
    {
        return $query->whereModelType($modelType)->whereModelId($modelId);
    }

    /**
     * @return bool
     */
    public function alreadyApplied()
    {
        return $this->times_left < 1;
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array  $models
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function newCollection(array $models = [])
    {
        return new RedeemedCouponCollection($models);
    }

    /**
     * Revoke the redeemed coupon. It will no longer be applied.
     *
     * @return self
     */
    public function revoke()
    {
        return tap($this, function () {
            $this->times_left = 0;
            $this->save();
        });
    }

    /**
     * Check whether the RedeemedCoupon applies to the next Order.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->times_left > 0;
    }
}
