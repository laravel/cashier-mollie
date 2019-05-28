<?php

namespace Laravel\Cashier\Exceptions;

use Throwable;

class CouponNotFoundException extends CouponException
{
    /**
     * CouponNotFoundException constructor.
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = 'Coupon not found', int $code = 404, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
