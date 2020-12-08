<?php

namespace Laravel\Cashier\Traits;

use Illuminate\Database\Eloquent\Model;

trait HasOwner
{
    /**
     * Retrieve the model's owner.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function owner()
    {
        return $this->morphTo('owner');
    }

    /**
     * Scope a query to only records for a specific owner.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Model $owner
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereOwner($query, $owner)
    {
        return $query
            ->where('owner_id', $owner->id)
            ->where('owner_type', $owner->getMorphClass());
    }
}
