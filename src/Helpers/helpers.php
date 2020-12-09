<?php

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;

if (! function_exists('object_to_array_recursive')) {
    /**
     * Recursively cast an object into an array.
     *
     * @param $object
     * @return array|null
     */
    function object_to_array_recursive($object)
    {
        if (empty($object)) {
            return null;
        }

        return json_decode(json_encode($object, JSON_FORCE_OBJECT), true);
    }
}

if (! function_exists('money')) {
    /**
     * Create a Money object from a Mollie Amount array.
     *
     * @param int $value
     * @param string $currency
     * @return \Money\Money
     */
    function money(int $value, string $currency)
    {
        return new Money($value, new Currency($currency));
    }
}

if (! function_exists('decimal_to_money')) {
    /**
     * Create a Money object from a decimal string / currency pair.
     *
     * @param string $value
     * @param string $currency
     * @return \Money\Money
     */
    function decimal_to_money(string $value, string $currency)
    {
        $moneyParser = new DecimalMoneyParser(new ISOCurrencies());

        return $moneyParser->parse($value, new Currency($currency));
    }
}

if (! function_exists('mollie_array_to_money')) {
    /**
     * Create a Money object from a Mollie Amount array.
     *
     * @param array $array
     * @return \Money\Money
     */
    function mollie_array_to_money(array $array)
    {
        return decimal_to_money($array['value'], $array['currency']);
    }
}

if (! function_exists('money_to_mollie_array')) {
    /**
     * Create a Mollie Amount array from a Money object.
     *
     * @param \Money\Money
     * @return array $array
     */
    function money_to_mollie_array(Money $money)
    {
        $moneyFormatter = new DecimalMoneyFormatter(new ISOCurrencies());

        return [
            'currency' => $money->getCurrency()->getCode(),
            'value' => $moneyFormatter->format($money),
        ];
    }
}

if (! function_exists('mollie_object_to_money')) {
    /**
     * Create a Money object from a Mollie Amount object.
     *
     * @param object $object
     * @return \Money\Money
     */
    function mollie_object_to_money(object $object)
    {
        return decimal_to_money($object->value, $object->currency);
    }
}

if (! function_exists('money_to_decimal')) {

    /**
     * Format the money as basic decimal
     *
     * @param \Money\Money $money
     * @return string|bool
     */
    function money_to_decimal(Money $money)
    {
        return (new DecimalMoneyFormatter(new ISOCurrencies()))->format($money);
    }
}
