<?php

namespace Laravel\Cashier\Exceptions;

use Exception;
use Throwable;

class UnauthorizedInvoiceAccessException extends Exception
{
    /**
     * InvalidInvoiceAccessException constructor.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = 'This user does not have access to this invoice.', int $code = 403, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
