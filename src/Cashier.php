<?php

namespace Laravel\Cashier;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Order\OrderItem;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;

class Cashier
{
    /**
     * The current currency.
     *
     * @var string
     */
    protected static $currency = 'eur';

    /**
     * The current currency symbol.
     *
     * @var string
     */
    protected static $currencySymbol = '€';

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
     * Set the default currency for this merchant.
     *
     * @param  string  $currency
     * @param  string|null  $symbol
     * @return void
     * @throws \Exception
     */
    public static function useCurrency($currency, $symbol = null)
    {
        static::$currency = $currency;

        static::useCurrencySymbol($symbol ?: static::guessCurrencySymbol($currency));
    }

    /**
     * Set the currency symbol to be used when formatting currency.
     *
     * @param  string  $symbol
     * @return void
     */
    public static function useCurrencySymbol($symbol)
    {
        static::$currencySymbol = $symbol;
    }

    /**
     * Guess the currency symbol for the given currency.
     *
     * @param  string  $currency
     * @return string
     * @throws \Exception
     */
    protected static function guessCurrencySymbol($currency)
    {
        switch (strtolower($currency)) {
            case 'usd':
            case 'aud':
            case 'cad':
                return '$';
            case 'eur':
                return '€';
            case 'gbp':
                return '£';
            default:
                throw new Exception('Unable to guess symbol for currency. Please explicitly specify it.');
        }
    }

    /**
     * Get the currency currently in use.
     *
     * @return string
     */
    public static function usesCurrency()
    {
        return static::$currency;
    }
    
    /**
     * Get the currency symbol currently in use.
     *
     * @return string
     */
    public static function usesCurrencySymbol()
    {
        return static::$currencySymbol;
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
