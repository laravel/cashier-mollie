<?php

namespace Laravel\Cashier\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Cashier\Credit\Credit;
use Laravel\Cashier\Events\BalanceTurnedStale;
use Laravel\Cashier\Events\OrderCreated;
use Laravel\Cashier\Events\OrderPaymentFailed;
use Laravel\Cashier\Events\OrderPaymentPaid;
use Laravel\Cashier\Events\OrderProcessed;
use Laravel\Cashier\Exceptions\InvalidMandateException;
use Laravel\Cashier\MandatedPayment\MandatedPaymentBuilder;
use Laravel\Cashier\Order\Contracts\MinimumPayment;
use Laravel\Cashier\Traits\HasOwner;
use LogicException;
use Mollie\Api\Resources\Mandate;
use Mollie\Api\Types\PaymentStatus;

/**
 * @method static create(array $data)
 */
class Order extends Model
{
    use HasOwner;
    use ConvertsToMoney;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'processed_at',
        'created_at',
        'updated_at',
    ];

    protected $guarded = [];

    /**
     * @return int
     */
    public function getBalanceAfterAttribute()
    {
        return (int) $this->getBalanceBefore()->subtract($this->getCreditUsed())->getAmount();
    }

    /**
     * Creates an order from a collection of OrderItems
     *
     * @param \Laravel\Cashier\Order\OrderItemCollection $items
     * @param array $overrides
     * @param bool $process_items
     * @return Order
     */
    public static function createFromItems(OrderItemCollection $items, $overrides = [], $process_items = true)
    {
        return DB::transaction(function () use ($items, $overrides, $process_items) {
            if ($process_items) {
                $items = $items->preprocess();
            }

            if ($items->currencies()->count() > 1) {
                throw new LogicException('Creating an order requires items to have a single currency.');
            }

            if ($items->owners()->count() > 1) {
                throw new LogicException('Creating an order requires items to have a single owner.');
            }

            $currency = $items->first()->currency;
            $owner = $items->first()->owner;

            $total = $items->sum('total');

            $order = static::create(array_merge([
                'owner_id' => $owner->id,
                'owner_type' => get_class($owner),
                'number' => static::numberGenerator()->generate(),
                'currency' => $currency,
                'subtotal' => $items->sum('subtotal'),
                'tax' => $items->sum('tax'),
                'total' => $total,
                'total_due' => $total,
            ], $overrides));

            $items->each(function (OrderItem $item) use ($order, $process_items) {
                $item->update(['order_id' => $order->id]);

                if ($process_items) {
                    $item->process();
                }
            });

            Event::dispatch(new OrderCreated($order));

            return $order;
        });
    }

    /**
     * Creates a processed order from a collection of OrderItems
     *
     * @param \Laravel\Cashier\Order\OrderItemCollection $items
     * @param array $overrides
     * @return Order
     */
    public static function createProcessedFromItems(OrderItemCollection $items, $overrides = [])
    {
        $order = static::createFromItems(
            $items,
            array_merge([
                'processed_at' => now(),
            ], $overrides),
            false
        );

        Event::dispatch(new OrderProcessed($order));

        return $order;
    }

    /**
     * @param $item
     * @param array $overrides
     * @return \Laravel\Cashier\Order\Order
     */
    public static function createProcessedFromItem($item, $overrides = [])
    {
        return static::createProcessedFromItems(new OrderItemCollection([$item]), $overrides);
    }

    /**
     * Processes the Order into Credit, Refund or Mollie Payment - whichever is appropriate.
     *
     * @return $this
     * @throws \Laravel\Cashier\Exceptions\InvalidMandateException
     */
    public function processPayment()
    {
        $this->update(['mollie_payment_id' => 'temp_'.Str::uuid()]);

        DB::transaction(function () {
            $owner = $this->owner;

            // Process user balance, if any
            if ($this->getTotal()->getAmount() > 0 && $owner->hasCredit($this->currency)) {
                $total = $this->getTotal();
                $this->balance_before = $owner->credit($this->currency)->value;

                $creditUsed = $owner->maxOutCredit($total);
                $this->credit_used = (int) $creditUsed->getAmount();
                $this->total_due = $total->subtract($creditUsed)->getAmount();
            }

            $minimumPaymentAmount = $this->ensureValidMandateAndMinimumPaymentAmountWhenTotalDuePositive();
            $totalDue = money($this->total_due, $this->currency);

            switch (true) {
                case $totalDue->isZero():
                    // No payment processing required
                    $this->mollie_payment_id = null;

                    break;

                case $totalDue->lessThan($minimumPaymentAmount):
                    // No payment processing required
                    $this->mollie_payment_id = null;

                    // Add credit to the owner's balance
                    $credit = Credit::addAmountForOwner($owner, money(-($this->total_due), $this->currency));

                    if (! $owner->hasActiveSubscriptionWithCurrency($this->currency)) {
                        Event::dispatch(new BalanceTurnedStale($credit));
                    }

                    break;

                case $totalDue->greaterThanOrEqual($minimumPaymentAmount):

                    // Create Mollie payment
                    $payment = (new MandatedPaymentBuilder(
                        $owner,
                        "Order " . $this->number,
                        $totalDue,
                        url(config('cashier.webhook_url')),
                        [
                            'metadata' => [
                                'temporary_mollie_payment_id' => $this->mollie_payment_id,
                            ],
                        ]
                    ))->create();

                    $this->mollie_payment_id = $payment->id;
                    $this->mollie_payment_status = 'open';

                    break;

                default:
                    break;
            }

            $this->processed_at = now();
            $this->save();
        });

        Event::dispatch(new OrderProcessed($this));

        return $this;
    }

    /**
     * The order's items.
     *
     * @return HasMany
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array  $models
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function newCollection(array $models = [])
    {
        return new OrderCollection($models);
    }

    /**
     * Get the invoice for this Order.
     *
     * @param null $id
     * @param null $date
     * @return \Laravel\Cashier\Order\Invoice
     */
    public function invoice($id = null, $date = null)
    {
        $invoice = (new Invoice(
            $this->currency,
            $id ?: $this->number,
            $date ?: $this->created_at
        ))->addItems($this->items)
            ->setStartingBalance($this->getBalanceBefore())
            ->setCompletedBalance($this->getBalanceAfter())
            ->setUsedBalance($this->getCreditUsed());

        $invoice->setReceiverAddress($this->owner->getInvoiceInformation());

        $extra_information = null;
        $owner = $this->owner;

        if (method_exists($owner, 'getExtraBillingInformation')) {
            $extra_information = $owner->getExtraBillingInformation();

            if (! empty($extra_information)) {
                $extra_information = explode("\n", $extra_information);

                if (is_array($extra_information) && ! empty($extra_information)) {
                    $invoice->setExtraInformation($extra_information);
                }
            }
        }

        return $invoice;
    }

    /**
     * Checks whether the order is processed.
     *
     * @return bool
     */
    public function isProcessed()
    {
        return ! empty($this->processed_at);
    }

    /**
     * Scope the query to only include processed orders.
     *
     * @param $query
     * @param bool $processed
     * @return Builder
     */
    public function scopeProcessed($query, $processed = true)
    {
        if ($processed) {
            return $query->whereNotNull('processed_at');
        }

        return $query->whereNull('processed_at');
    }

    /**
     * Scope the query to only include unprocessed orders.
     *
     * @param $query
     * @param bool $unprocessed
     * @return Builder
     */
    public function scopeUnprocessed($query, $unprocessed = true)
    {
        return $query->processed(! $unprocessed);
    }

    /**
     * Scope the query to only include orders with a specific Mollie payment status.
     *
     * @param $query
     * @param string $status
     * @return Builder
     */
    public function scopePaymentStatus($query, $status)
    {
        return $query->where('mollie_payment_status', $status);
    }

    /**
     * Scope the query to only include paid orders.
     *
     * @param $query
     * @return Builder
     */
    public function scopePaid($query)
    {
        return $this
            ->scopePaymentStatus($query, PaymentStatus::STATUS_PAID)
            ->orWhere('total_due', '=', 0);
    }

    /**
     * Retrieve an Order by the Mollie Payment id.
     *
     * @param $id
     * @return self
     */
    public static function findByPaymentId($id)
    {
        return self::where('mollie_payment_id', $id)->first();
    }

    /**
     * Retrieve an Order by the Mollie Payment id or throw an Exception if not found.
     *
     * @param $id
     * @return self
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findByPaymentIdOrFail($id)
    {
        return self::where('mollie_payment_id', $id)->firstOrFail();
    }

    /**
     * Checks whether credit was used in the Order.
     * The credit applied will be reset to 0 when an Order payment fails.
     *
     * @return bool
     */
    public function creditApplied()
    {
        return $this->credit_used <> 0;
    }

    /**
     * Handles a failed payment for the Order.
     * Restores any credit used to the customer's balance and resets the credits applied to the Order.
     * Invokes handlePaymentFailed() on each related OrderItem.
     *
     * @return $this
     */
    public function handlePaymentFailed()
    {
        return DB::transaction(function () {
            if ($this->creditApplied()) {
                $this->owner->addCredit($this->getCreditUsed());
            }

            $this->update([
                'mollie_payment_status' => 'failed',
                'balance_before' => 0,
                'credit_used' => 0,
            ]);

            Event::dispatch(new OrderPaymentFailed($this));

            $this->items->each(function (OrderItem $item) {
                $item->handlePaymentFailed();
            });

            $this->owner->validateMollieMandate();

            return $this;
        });
    }

    /**
     * Handles a paid payment for this order.
     * Invokes handlePaymentPaid() on each related OrderItem.
     *
     * @return $this
     */
    public function handlePaymentPaid()
    {
        return DB::transaction(function () {
            $this->update(['mollie_payment_status' => 'paid']);
            Event::dispatch(new OrderPaymentPaid($this));

            $this->items->each(function (OrderItem $item) {
                $item->handlePaymentPaid();
            });

            return $this;
        });
    }

    /**
     * @return \Money\Money
     */
    public function getSubtotal()
    {
        return $this->toMoney($this->subtotal);
    }

    /**
     * @return \Money\Money
     */
    public function getTax()
    {
        return $this->toMoney($this->tax);
    }

    /**
     * @return \Money\Money
     */
    public function getTotal()
    {
        return $this->toMoney($this->total);
    }

    /**
     * @return \Money\Money
     */
    public function getTotalDue()
    {
        return $this->toMoney($this->total_due);
    }

    /**
     * @return \Money\Money
     */
    public function getBalanceBefore()
    {
        return $this->toMoney($this->balance_before);
    }

    /**
     * @return \Money\Money
     */
    public function getBalanceAfter()
    {
        return $this->toMoney($this->balance_after);
    }

    /**
     * @return \Money\Money
     */
    public function getCreditUsed()
    {
        return $this->toMoney($this->credit_used);
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param \Mollie\Api\Resources\Mandate $mandate
     * @throws \Laravel\Cashier\Exceptions\InvalidMandateException
     */
    protected function guardMandate(?Mandate $mandate)
    {
        if (empty($mandate) || ! $mandate->isValid()) {
            throw new InvalidMandateException('Cannot process payment without valid mandate for order id '.$this->id);
        }
    }

    /**
     * @return \Laravel\Cashier\Order\OrderNumberGenerator
     */
    protected static function numberGenerator()
    {
        return app()->make(config('cashier.order_number_generator.model'));
    }

    /**
     * @return \Money\Money
     * @throws InvalidMandateException
     */
    private function ensureValidMandateAndMinimumPaymentAmountWhenTotalDuePositive(): \Money\Money
    {
        // If the total due amount is below 0 checking for a mandate doesn't make sense.
        if ((int) $this->getTotalDue()->getAmount() > 0) {
            $mandate = $this->owner->mollieMandate();
            $this->guardMandate($mandate);
            $minimumPaymentAmount = app(MinimumPayment::class)::forMollieMandate($mandate, $this->getCurrency());
        } else {
            $minimumPaymentAmount = money(0, $this->getCurrency());
        }

        return $minimumPaymentAmount;
    }
}
