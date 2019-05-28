<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Throwable;

class PlanNotFoundException extends Exception
{
    /**
     * PlanNotFoundException constructor.
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = 'Plan not found', int $code = 404, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
