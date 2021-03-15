<?php
declare(strict_types=1);

namespace Laravel\Cashier\OneOffPayment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Events\OrderProcessed;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Traits\HasOwner;

/**
 * @property int id
 * @property string owner_type
 * @property int owner_id
 * @property int|null order_id
 * @property \Laravel\Cashier\OneOffPayment\TabItemCollection items
 * @property Order order
 */
class Tab extends Model
{
    use HasOwner;

    protected $guarded = [];

    /**
     * Create a new Tab Collection instance.
     *
     * @param  array  $models
     *
     * @return \Laravel\Cashier\OneOffPayment\TabCollection
     */
    public function newCollection(array $models = [])
    {
        return new TabCollection($models);
    }

    /**
     * Scope the query to only include unprocessed tab.
     *
     * @param $query
     * @param bool $processed
     * @return Builder
     */
    public function scopeProcessed($query, $processed = true)
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
    public function scopeUnprocessed($query, $unprocessed = true)
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
    public function scopeDue($query)
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
    public function scopeShouldProcess($query)
    {
        return $query->unprocessed()->due();
    }

    public function items(): HasMany
    {
        return $this->hasMany(TabItem::class);
    }

    public function add($description, $amount, $overrides = []): Model
    {
        $defaultOptions = [
            'unit_price' => $amount,
            'tax_percentage' => $this->tax_percentage,
            'description' => $description,
        ];
        $attributes = array_merge($defaultOptions, $overrides, [
            'owner_type' => $this->getMorphClass(),
            'owner_id' => $this->getKey(),
        ]);

        return $this->items()->create($attributes);
    }

    public function execute($process_at = null)
    {
        $this->process_at = $process_at ?? now()->subMinute();

        $this->save();

        return $this;
    }

    public function handleProcessed(): self
    {
        $tabItems = $this->items;

        DB::transaction(function () use ($tabItems) {
            $orderItems = $tabItems->toNewOrderItemCollection()->save();
            $order = Order::createProcessedFromItems($orderItems);

            $this->order_id = $order->id;

            $this->save();
        });

        event(new OrderProcessed($this));

        return $this;
    }
}