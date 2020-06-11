<?php

namespace Laravel\Cashier\Traits;

trait PaymentMethodString
{
    /**
     * Backwards compatible: split strings into array
     *
     * @param  string  $method
     *
     * @return string[]
     */
    private function castPaymentMethodString(string $method)
    {
        $method = trim($method);

        if (empty($method)) {
            return [];
        }

        $methodList = explode(',', $method);

        return array_map(
            function ($method) {
                return trim($method);
            },
            $methodList
        );
    }
}
