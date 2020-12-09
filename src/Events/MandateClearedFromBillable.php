<?php

namespace Laravel\Cashier\Events;

use Illuminate\Queue\SerializesModels;

class MandateClearedFromBillable
{
    use SerializesModels;

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
     * @param mixed $owner
     * @param string $oldMandateId
     */
    public function __construct($owner, string $oldMandateId)
    {
        $this->owner = $owner;
        $this->oldMandateId = $oldMandateId;
    }
}
