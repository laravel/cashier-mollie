<?php
declare(strict_types=1);

namespace Laravel\Cashier\Refunds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int id
 * @property string mollie_refund_id
 * @property string mollie_refund_status
 * @property string owner_type
 * @property int owner_id
 * @property int|null original_order_item_id
 * @property int|null original_order_id
 * @property int|null order_id
 * @property \Laravel\Cashier\Refunds\RefundItemCollection items
 */
class Refund extends Model
{
    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }
}
