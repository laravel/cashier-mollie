<?php

namespace Laravel\Cashier\Coupon;

use Illuminate\Database\Eloquent\Collection;
use Laravel\Cashier\Order\OrderItem;
use Laravel\Cashier\Order\OrderItemCollection;

class RedeemedCouponCollection extends Collection
{
    public function applyTo(OrderItem $item)
    {
        return $this->reduce(
            function (OrderItemCollection $carry, RedeemedCoupon $coupon) {
                return $coupon->applyTo($carry);
            },
            $item->toCollection()
        );
    }
}
