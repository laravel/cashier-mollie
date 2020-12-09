<?php

declare(strict_types=1);

namespace Laravel\Cashier\Plan;

use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\PlanNotFoundException;
use Laravel\Cashier\Order\OrderItemPreprocessorCollection as Preprocessors;
use Laravel\Cashier\Plan\Contracts\PlanRepository;

class ConfigPlanRepository implements PlanRepository
{
    /**
     * Get a plan by its name.
     *
     * @param $name
     * @return null|\Laravel\Cashier\Plan\Contracts\Plan
     */
    public static function find(string $name)
    {
        $defaults = config('cashier_plans.defaults');

        if (array_key_exists($name, config('cashier_plans.plans'))) {
            return static::populatePlan($name, config('cashier_plans.plans.'.$name), $defaults);
        }

        return null;
    }

    /**
     * Get a plan by its name or throw an exception.
     *
     * @param string $name
     * @return \Laravel\Cashier\Plan\Contracts\Plan
     * @throws PlanNotFoundException
     */
    public static function findOrFail(string $name)
    {
        if (($result = self::find($name)) != null) {
            return $result;
        } else {
            throw new PlanNotFoundException;
        }
    }

    /**
     * @return \Laravel\Cashier\Plan\PlanCollection
     */
    protected static function all()
    {
        $result = [];
        $defaults = config('cashier_plans.defaults');

        foreach (config('cashier_plans.plans') as $name => $configArray) {
            $result[] = static::populatePlan($name, $configArray, $defaults);
        }

        return new PlanCollection($result);
    }

    /**
     * @param string $name
     * @param array $planConfig
     * @param array $planDefaults
     * @return \Laravel\Cashier\Plan\Plan
     */
    public static function populatePlan(string $name, array $planConfig, array $planDefaults = [])
    {
        $plan = new Plan($name);

        foreach (static::toPlanArray($planConfig, $planDefaults) as $key => $value) {
            $key = Str::camel($key);

            switch ($key) {
                case 'amount':
                    $plan->setAmount(mollie_array_to_money($value));

                    break;
                case 'firstPaymentAmount':
                    $plan->setFirstPaymentAmount(mollie_array_to_money($value));

                    break;
                case 'firstPaymentMethod':
                    $plan->setFirstPaymentMethod($value);

                    break;
                case 'orderItemPreprocessors':
                    $plan->setOrderItemPreprocessors(Preprocessors::fromArray($value));

                    break;
                default: // call $plan->setKey() if setKey method exists
                    $method = 'set' . ucfirst($key);
                    if (method_exists($plan, $method)) {
                        $plan->$method($value);
                    }

                    break;
            }
        }

        return $plan;
    }

    /**
     * @param array $planConfig
     * @param array $planDefaults
     * @return array
     */
    protected static function toPlanArray(array $planConfig, array $planDefaults = [])
    {
        $result = array_merge($planDefaults, $planConfig);

        // Flatten and prefix first_payment
        if (array_key_exists('first_payment', $result)) {
            $firstPaymentDefaults = $result['first_payment'];
            unset($result['first_payment']);

            foreach ($firstPaymentDefaults as $key => $value) {
                $newKey = Str::camel('first_payment_' . $key);
                $result[ $newKey ] = $value;
            }
        }

        return $result;
    }
}
