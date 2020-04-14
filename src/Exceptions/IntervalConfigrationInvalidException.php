<?php

namespace Laravel\Cashier\Exceptions;

use LogicException;
use Throwable;

class IntervalConfigrationInvalidException extends LogicException
{
    /**
     * @param string $message
     */
    public function __construct(string $message = 'Interval configuration can only be a string or an array')
    {
        parent::__construct($message);
    }
}
