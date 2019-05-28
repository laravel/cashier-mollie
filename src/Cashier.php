<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;

class Cashier
{
    /**
     * The custom currency formatter.
     *
     * @var callable
     */
    protected static $formatCurrencyUsing;

    /**
     * Process scheduled OrderItems
     *
     * @return \Illuminate\Support\Collection
     */
    public static function run()
    {
        $items = OrderItem::shouldProcess()->get();

        $orders = $items->chunkByOwnerAndCurrency()->map(function ($chunk) {
            return Order::createFromItems($chunk)->processPayment();
        });

        return $orders;
    }

    /**
     * Set the custom currency formatter.
     *
     * @param  callable  $callback
     * @return void
     */
    public static function formatCurrencyUsing(callable $callback)
    {
        static::$formatCurrencyUsing = $callback;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param \Money\Money $money
     * @return string
     */
    public static function formatAmount(Money $money)
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $money);
        }

        $numberFormatter = new \NumberFormatter('de_DE', \NumberFormatter::CURRENCY);
        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies);

        return $moneyFormatter->format($money);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @return null|string
     */
    public static function getLocale(Model $owner)
    {
        if(method_exists($owner, 'getLocale')) {
            $locale = $owner->getLocale();

            if(!empty($locale)) {
                return $locale;
            }
        }

        return config('cashier.locale');
    }
}
