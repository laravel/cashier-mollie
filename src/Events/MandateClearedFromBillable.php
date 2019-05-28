<?php

namespace Laravel\Cashier\Events;

class MandateClearedFromBillable
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $owner;

    /**
     * @var string
     */
    public $oldMandateId;

    /**
     * ClearedMandate constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param string $oldMandateId
     */
    public function __construct($owner, string $oldMandateId)
    {
        $this->owner = $owner;
        $this->oldMandateId = $oldMandateId;
    }}
