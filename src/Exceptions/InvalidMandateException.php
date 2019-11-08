<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Throwable;

class InvalidMandateException extends Exception
{
    /**
     * PlanNotFoundException constructor.
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = 'Invalid customer mandate', int $code = 404, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
