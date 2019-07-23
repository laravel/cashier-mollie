<?php

namespace Laravel\Cashier\Events;

use Illuminate\Database\Eloquent\Model;

class MandateUpdated
{
    /** @var \Illuminate\Database\Eloquent\Model */
    public $owner;

    /**
     * MandateUpdated constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     */
    public function __construct(Model $owner)
    {
        $this->owner = $owner;
    }
}
