# F.A.Q.

### My billable model uses UUIDs, how can I get Cashier Mollie to work with this?
By default Cashier Mollie uses `unsignedInteger` fields for the billable model relationships.
If required for your billable model, modify the cashier migrations for UUIDs:

```php
// Replace this:
$table->unsignedInteger('owner_id');
    
// By this:
$table->uuid('owner_id');  
```

### How is prorating handled?

Cashier Mollie applies prorating by default. With prorating, customers are billed at the start of each billing cycle.

This means that when the subscription quantity is updated or is switched to another plan:

1. the billing cycle is reset
2. the customer is credited for unused time, meaning that the amount that was overpaid is added to the customer's balance.
3. a new billing cycle is started with the new subscription settings. An Order (and payment) is generated to deal with
   all of the previous, including applying the credited balance to the Order.

This does not apply to the `$subscription->swapNextCycle('other-plan')`, which simply waits for the next billing cycle
to update the subscription plan. A common use case for this is downgrading the plan at the end of the billing cycle.

### How can I load coupons and/or plans from database?

Because Cashier Mollie uses contracts a lot it's quite easy to extend Cashier Mollie and use your own implementations.
You can load coupons/plans from database, a file or even a JSON API.

For example a simple implementation of plans from the database:

Firstly you create your own implementation of the plan repository and implement `Laravel\Cashier\Plan\Contracts\PlanRepository`.
Implement the methods according to your needs and make sure you'll return a `Laravel\Cashier\Plan\Contracts\Plan`.

```php
use App\Plan;
use Laravel\Cashier\Exceptions\PlanNotFoundException;
use Laravel\Cashier\Plan\Contracts\PlanRepository;

class DatabasePlanRepository implements PlanRepository
{
    public static function find(string $name)
    {
        $plan = Plan::where('name', $name)->first();

        if (is_null($plan)) {
            return null;
        }

        // Return a \Laravel\Cashier\Plan\Plan by creating one from the database values
        return $plan->buildCashierPlan();

        // Or if your model implements the contract: \Laravel\Cashier\Plan\Contracts\Plan
        return $plan;
    }

    public static function findOrFail(string $name)
    {
        if (($result = self::find($name)) === null) {
            throw new PlanNotFoundException;
        }

        return $result;
    }
}
```
::: details Example Plan model (app/Plan.php) with buildCashierPlan and returns a \Laravel\Cashier\Plan\Plan
```php
<?php

namespace App;

use Laravel\Cashier\Plan\Plan as CashierPlan;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    /**
     * Builds a Cashier plan from the current model.
     *
     * @returns \Laravel\Cashier\Plan\Plan
     */
    public function buildCashierPlan(): CashierPlan
    {
        $plan = new CashierPlan($this->name);
        
        return $plan->setAmount(mollie_array_to_money($this->amount))
            ->setInterval($this->interval)
            ->setDescription($this->description)
            ->setFirstPaymentMethod($this->first_payment_method)
            ->setFirstPaymentAmount(mollie_array_to_money($this->first_payment_amount))
            ->setFirstPaymentDescription($this->first_payment_description)
            ->setFirstPaymentRedirectUrl($this->first_payment_redirect_url)
            ->setFirstPaymentWebhookUrl($this->first_payment_webhook_url)
            ->setOrderItemPreprocessors(Preprocessors::fromArray($this->order_item_preprocessors));
    }
}
```

Note: In this case you'll need to add accessors for all the values (like amount, interval, fist_payment_method etc.)
to make sure you'll use the values from your defaults (config/cashier_plans.php > defaults).
:::

Then you just have to bind your implementation to the Laravel/Illuminate container by registering the binding in a service provider

```php
class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(\Laravel\Cashier\Plan\Contracts\PlanRepository::class, DatabasePlanRepository::class);
    }
}
```

Cashier Mollie will now use your implementation of the PlanRepository. For coupons this is basically the same,
just make sure you implement the CouponRepository contract and bind the contract to your own implementation.