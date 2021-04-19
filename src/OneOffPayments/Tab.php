<?php
declare(strict_types=1);

namespace Laravel\Cashier\OneOffPayments;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse;
use Laravel\Cashier\Traits\HasOwner;
use Money\Money;

/**
 * @property int id
 * @property string owner_type
 * @property int owner_id
 * @property int|null order_id
 * @property Order order
 * @property Carbon process_at
 * @property Carbon scheduled_at
 */
class Tab extends Model
{
    use HasOwner;

    protected $guarded = [];

    protected $casts = [
        'process_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    /**
     * Scope the query to only include unprocessed tab.
     *
     * @param $query
     * @param bool $processed
     * @return Builder
     */
    public function scopeWhereProcessed($query, $processed = true)
    {
        if (! $processed) {
            return $query->whereNull('order_id');
        }

        return $query->whereNotNull('order_id');
    }

    /**
     * Scope the query to only include unprocessed order items.
     *
     * @param $query
     * @param bool $unprocessed
     * @return Builder
     */
    public function scopeWhereUnprocessed($query, $unprocessed = true)
    {
        return $query->processed(! $unprocessed);
    }

    /**
     * Limits the query to Order Items that are past the process_at date.
     * This includes both processed and unprocessed items.
     *
     * @param $query
     * @return mixed
     */
    public function scopeWhereDue($query)
    {
        return $query->where('process_at', '<=', now());
    }

    /**
     * Limits the query to Order Items that are ready to be processed.
     * This includes items that are both unprocessed and due.
     *
     * @param $query
     * @return mixed
     */
    public function scopeWhereShouldProcess($query)
    {
        return $query->unprocessed()->due();
    }

    public function items(): HasMany
    {
        return $this->hasMany(TabItem::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }

    public function addItem(string $description, Money $unitPrice): TabItemBuilder
    {
        return new TabItemBuilder($this, $description, $unitPrice);
    }

    public function close(): RedirectToCheckoutResponse | Order
    {
        if ($this->owner->validateMollieMandate()) {
            // TODO return new pending Order (MandatedPaymentBuilder)
        }

        // TODO return RedirectToCheckoutResponse (FirstPaymentBuilder)
    }

    public function cancel()
    {
        // TODO use soft deletes
        $this->items()->delete();
        $this->delete();
    }
}
