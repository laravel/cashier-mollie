<?php

namespace Laravel\Cashier\Order;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Money\Currency;

class OrderItemCollection extends Collection
{
    /**
     * Get a collection of distinct currencies in this collection.
     * @return \Illuminate\Support\Collection
     */
    public function currencies()
    {
        return collect(array_values($this->pluck('currency')->unique()->all()));
    }

    /**
     * Get the distinct owners for this collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function owners()
    {
        return $this->unique(function ($item) {
            return $item->owner_type . $item->owner_id;
        })->map(function ($item) {
            return $item->owner;
        });
    }

    /**
     * Filter this collection by owner.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function whereOwner($owner)
    {
        return $this->filter(function ($item) use ($owner) {
            return (string) $item->owner_id === (string) $owner->id
                && $item->owner_type === get_class($owner);
        });
    }

    /**
     * Returns a collection of OrderItemCollections, grouped by owner.
     *
     * @return \Illuminate\Support\Collection
     */
    public function chunkByOwner()
    {
        return $this->owners()->sortBy(function ($owner) {
            return get_class($owner) . '_' . $owner->id;
        })->mapWithKeys(function ($owner) {
            $key = get_class($owner) . '_' . $owner->id;

            return [$key => $this->whereOwner($owner)];
        });
    }

    /**
     * Filter this collection by currency symbol.
     *
     * @param $currency
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function whereCurrency($currency)
    {
        return $this->where('currency', $currency);
    }

    /**
     * Returns a collection of OrderItemCollections, grouped by currency symbol.
     *
     * @return \Illuminate\Support\Collection
     */
    public function chunkByCurrency()
    {
        return $this->currencies()
            ->sort()
            ->mapWithKeys(function ($currency) {
                return [$currency => $this->whereCurrency($currency)];
            });
    }

    /**
     * Returns a collection of OrderItemCollections, grouped by currency symbol AND owner.
     *
     * @return \Illuminate\Support\Collection
     */
    public function chunkByOwnerAndCurrency()
    {
        $result = collect();

        $this->chunkByOwner()->each(function ($owners_chunks, $owner_reference) use (&$result) {
            $owners_chunks->chunkByCurrency()->each(function ($chunk, $currency) use (&$result, $owner_reference) {
                $key = "{$owner_reference}_{$currency}";
                $result->put($key, $chunk);
            });
        });

        return $result;
    }

    /**
     * Preprocesses the OrderItems.
     *
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public function preprocess()
    {
        /** @var BaseCollection $items */
        $items = $this->flatMap(function (OrderItem $item) {
            return $item->preprocess();
        });

        return static::fromBaseCollection($items);
    }

    /**
     * Create an OrderItemCollection from a basic Collection.
     *
     * @param \Illuminate\Support\Collection $collection
     * @return \Laravel\Cashier\Order\OrderItemCollection
     */
    public static function fromBaseCollection(BaseCollection $collection)
    {
        return new static($collection->all());
    }

    /**
     * Persist all items in the collection.
     *
     * @return \Illuminate\Support\Collection|\Laravel\Cashier\Order\OrderItemCollection
     */
    public function save()
    {
        return $this->map(function (OrderItem $item) {
            $item->save();

            return $item;
        });
    }

    /**
     * Get a collection of distinct tax percentages in this collection.
     * @return \Illuminate\Support\Collection
     */
    public function taxPercentages()
    {
        return collect(array_values($this->pluck('tax_percentage')->unique()->sort()->all()));
    }
}
