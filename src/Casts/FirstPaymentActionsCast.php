<?php
namespace Laravel\Cashier\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class FirstPaymentActionsCast implements CastsAttributes
{
    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     *
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        return json_decode($value);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     *
     * @return mixed
     */
    public function set($model, string $key, $value, array $attributes)
    {
        return json_encode($value);
    }
}
