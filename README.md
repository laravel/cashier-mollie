<p align="center">
  <img src="https://info.mollie.com/hubfs/github/laravel-cashier/logoLaravel.jpg" width="128" height="128"/>
</p>
<h1 align="center">Subscription billing with Laravel Cashier for Mollie</h1>

<img src="https://info.mollie.com/hubfs/github/laravel-cashier/editorLaravel.jpg" />

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laravel/cashier-mollie.svg?style=flat-square)](https://packagist.org/packages/laravel/cashier-mollie)
[![Github Actions](https://github.com/laravel/cashier-mollie/workflows/tests/badge.svg)](https://github.com/laravel/cashier-mollie/actions)

Laravel Cashier provides an expressive, fluent interface to subscriptions using [Mollie](https://www.mollie.com)'s billing services.

## Installation

First, make sure to add the Mollie key to your `.env` file. You can obtain an API key from the [Mollie dashboard](https://www.mollie.com/dashboard/developers/api-keys):

```dotenv
MOLLIE_KEY="test_xxxxxxxxxxx"
```

Next, pull this package in using composer:

```bash
composer require laravel/cashier-mollie "^1.0"
```

## Setup

Once you have pulled in the package:

1. Run `php artisan cashier:install`. 

2. Add these fields to your billable model's migration (typically the default "create_user_table" migration):

    ```php
    $table->string('mollie_customer_id')->nullable();
    $table->string('mollie_mandate_id')->nullable();
    $table->decimal('tax_percentage', 6, 4)->default(0); // optional
    $table->dateTime('trial_ends_at')->nullable(); // optional
    $table->text('extra_billing_information')->nullable(); // optional
    ```

3. Run the migrations: `php artisan migrate`

4. Ensure you have properly configured the `MOLLIE_KEY` in your .env file. You can obtain an API key from the [Mollie dashboard](https://www.mollie.com/dashboard/developers/api-keys):

    ```dotenv
   MOLLIE_KEY="test_xxxxxxxxxxxxxxxxxxxxxx"
    ```

5. Prepare the configuration files:

    - configure at least one subscription plan in `config/cashier_plans.php`.
    
    - in `config/cashier_coupons.php` you can manage any coupons. By default an example coupon is enabled. Consider
    disabling it before deploying to production. 
    
    - the base configuration is in `config/cashier`. Be careful while modifying this, in most cases you will not need
    to.

6. Prepare the billable model (typically the default Laravel User model):
    
    - Add the `Laravel\Cashier\Billable` trait.
    
    - Optionally, override the method `mollieCustomerFields()` to configure what billable model fields are stored while creating the Mollie Customer.
    Out of the box the `mollieCustomerFields()` method uses the default Laravel User model fields:
     
    ```php
    public function mollieCustomerFields() {
        return [
            'email' => $this->email,
            'name' => $this->name,
        ];
    }
    ```
    Learn more about storing data on the Mollie Customer [here](https://docs.mollie.com/reference/v2/customers-api/create-customer#parameters).
    
    - Implement `Laravel\Cashier\Order\Contracts\ProvidesInvoiceInformation` interface. For example:
    
    ```php
   /**
    * Get the receiver information for the invoice.
    * Typically includes the name and some sort of (E-mail/physical) address.
    *
    * @return array An array of strings
    */
   public function getInvoiceInformation()
   {
       return [$this->name, $this->email];
   }

   /**
    * Get additional information to be displayed on the invoice. Typically a note provided by the customer.
    *
    * @return string|null
    */
   public function getExtraBillingInformation()
   {
       return null;
   }
    ```

7. Schedule a periodic job to execute `Cashier::run()`.
   
    ```php
    $schedule->command('cashier:run')
        ->daily() // run as often as you like (Daily, monthly, every minute, ...)
        ->withoutOverlapping(); // make sure to include this
    ```
   
You can find more about scheduling jobs using Laravel [here](https://laravel.com/docs/scheduling).

🎉 You're now good to go :).

## Usage

### Creating subscriptions

To create a subscription, first retrieve an instance of your billable model, which typically will be an instance of
`App\User`. Once you have retrieved the model instance, you may use the `newSubscription` method to create the model's
subscription:

```php
$user = User::find(1);
// Make sure to configure the 'premium' plan in config/cashier_plans.php
$result = $user->newSubscription('main', 'premium')->create();
```

If the customer already has a valid Mollie mandate, the `$result` will be a `Subscription`.

If the customer has no valid Mollie mandate yet, the `$result` will be a `RedirectToCheckoutResponse`, redirecting the
customer to the Mollie checkout to make the first payment. Once the payment has been received the subscription will
start.

Here's a basic controller example for creating the subscription:

```php
namespace App\Http\Controllers;

use Laravel\Cashier\SubscriptionBuilder\RedirectToCheckoutResponse;
use Illuminate\Support\Facades\Auth;

class CreateSubscriptionController extends Controller
{
    /**
     * @param string $plan
     * @return \Illuminate\Http\RedirectResponse
     */
    public function __invoke(string $plan)
    {
        $user = Auth::user();

        $name = ucfirst($plan) . ' membership';

        if(!$user->subscribed($name, $plan)) {

            $result = $user->newSubscription($name, $plan)->create();

            if(is_a($result, RedirectToCheckoutResponse::class)) {
                return $result; // Redirect to Mollie checkout
            }

            return back()->with('status', 'Welcome to the ' . $plan . ' plan');
        }

        return back()->with('status', 'You are already on the ' . $plan . ' plan');
    }
}
```

In order to always enforce a redirect to the Mollie checkout page, use the `newSubscriptionViaMollieCheckout` method
instead of `newSubscription`:

    $redirect = $user->newSubscriptionViaMollieCheckout('main', 'premium')->create(); // make sure to configure the 'premium' plan in config/cashier.php

### Coupons

Coupon handling in Cashier Mollie is designed with full flexibility in mind.

Coupons can be defined in `config/cashier_coupons.php`.

You can provide your own coupon handler by extending
`\Cashier\Discount\BaseCouponHandler`.

Out of the box, a basic `FixedDiscountHandler` is provided.

#### Redeeming a coupon for an existing subscription

For redeeming a coupon for an existing subscription, use the `redeemCoupon()` method on the billable trait:

    $user->redeemCoupon('your-coupon-code');

This will validate the coupon code and redeem it. The coupon will be applied to the upcoming Order.

Optionally, specify the subscription it should be applied to:

    $user->redeemCoupon('your-coupon-code', 'main');
    
By default all other active redeemed coupons for the subscription will be revoked. You can prevent this by setting the
`$revokeOtherCoupons` flag to false:

    $user->redeemCoupon('your-coupon-code', 'main', false);

### Checking subscription status

Once a user is subscribed to your application, you may easily check their subscription status using a variety of convenient methods. First, the `subscribed` method returns `true` if the user has an active subscription, even if the subscription is currently within its trial period:

```php
if ($user->subscribed('main')) {
    //
}
```

The `subscribed` method also makes a great candidate for a [route middleware](https://www.laravel.com/docs/middleware), allowing you to filter access to routes and controllers based on the user's subscription status:

```php
public function handle($request, Closure $next)
{
    if ($request->user() && ! $request->user()->subscribed('main')) {
        // This user is not a paying customer...
        return redirect('billing');
    }

    return $next($request);
}
```

If you would like to determine if a user is still within their trial period, you may use the `onTrial` method. This method can be useful for displaying a warning to the user that they are still on their trial period:

```php
if ($user->subscription('main')->onTrial()) {
    //
}
```

The `subscribedToPlan` method may be used to determine if the user is subscribed to a given plan based on a configured plan. In this example, we will determine if the user's `main` subscription is actively subscribed to the `monthly` plan:

```php
if ($user->subscribedToPlan('monthly', 'main')) {
    //
}
```
    
### Cancelled Subscription Status

To determine if the user was once an active subscriber, but has cancelled their subscription, you may use the `cancelled` method:

```php
if ($user->subscription('main')->cancelled()) {
    //
}
```

You may also determine if a user has cancelled their subscription, but are still on their "grace period" until the subscription fully expires. For example, if a user cancels a subscription on March 5th that was originally scheduled to expire on March 10th, the user is on their "grace period" until March 10th. Note that the `subscribed` method still returns `true` during this time:

```php
if ($user->subscription('main')->onGracePeriod()) {
    //
}
```

### Changing Plans

After a user is subscribed to your application, they may occasionally want to change to a new subscription plan. To swap a user to a new subscription, pass the plan's identifier to the `swap` or `swapNextCycle` method:

```php
$user = App\User::find(1);

// Swap right now
$user->subscription('main')->swap('other-plan-id');

// Swap once the current cycle has completed
$user->subscription('main')->swapNextCycle('other-plan-id');
```

If the user is on trial, the trial period will be maintained. Also, if a "quantity" exists for the subscription, that quantity will also be maintained.

### Subscription Quantity

Sometimes subscriptions are affected by "quantity". For example, your application might charge €10 per month **per user** on an account. To easily increment or decrement your subscription quantity, use the `incrementQuantity` and `decrementQuantity` methods:

```php
$user = User::find(1);

$user->subscription('main')->incrementQuantity();

// Add five to the subscription's current quantity...
$user->subscription('main')->incrementQuantity(5);

$user->subscription('main')->decrementQuantity();

// Subtract five to the subscription's current quantity...
$user->subscription('main')->decrementQuantity(5);
```

Alternatively, you may set a specific quantity using the `updateQuantity` method:

```php
$user->subscription('main')->updateQuantity(10);
```

### Subscription Taxes

To specify the tax percentage a user pays on a subscription, implement the `taxPercentage` method on your billable model, and return a numeric value between 0 and 100, with no more than 2 decimal places.

```php
public function taxPercentage() {
    return 20;
}
```

The `taxPercentage` method enables you to apply a tax rate on a model-by-model basis, which may be helpful for a user base that spans multiple countries and tax rates.

#### Syncing Tax Percentages

When changing the hard-coded value returned by the `taxPercentage` method, the tax settings on any existing subscriptions for the user will remain the same. If you wish to update the tax value for existing subscriptions with the returned `taxPercentage` value, you should call the `syncTaxPercentage` method on the user's subscription instance:

```php
$user->subscription('main')->syncTaxPercentage();
```
    
### Subscription Anchor Date

Not (yet) implemented, but you could make this work by scheduling `Cashier::run()` to only execute on a specific day of the month.

### Cancelling Subscriptions

To cancel a subscription, call the `cancel` method on the user's subscription:

```php
$user->subscription('main')->cancel();
```
or
```php
$user->subscription('main')->cancelAt(now());
```

When a subscription is cancelled, Cashier will automatically set the `ends_at` column in your database. This column is used to know when the `subscribed` method should begin returning `false`. For example, if a customer cancels a subscription on March 1st, but the subscription was not scheduled to end until March 5th, the `subscribed` method will continue to return `true` until March 5th.

You may determine if a user has cancelled their subscription but are still on their "grace period" using the `onGracePeriod` method:

```php
if ($user->subscription('main')->onGracePeriod()) {
    //
}
```

### Resuming Subscriptions

If a user has cancelled their subscription and you wish to resume it, use the `resume` method. The user **must** still be on their grace period in order to resume a subscription:

    $user->subscription('main')->resume();

If the user cancels a subscription and then resumes that subscription before the subscription has fully expired, they will not be billed immediately. Instead, their subscription will be re-activated, and they will be billed on the original billing cycle.

### Updating Customer payment mandates

Coming soon.

### Subscription Trials

#### With Mandate Up Front

If you would like to offer trial periods to your customers while still collecting payment method information up front, you should use the `trialDays` method when creating your subscriptions:

```
$user = User::find(1);

$user->newSubscription('main', 'monthly')
            ->trialDays(10)
            ->create();
```

This method will set the trial period ending date on the subscription record within the database.

> {note} The customer will be redirected to the Mollie checkout page to make the first payment in order to register a mandate. You can modify the amount in the cashier config file. 

> {note} If the customer's subscription is not cancelled before the trial ending date they will be charged as soon as the trial expires, so you should be sure to notify your users of their trial ending date.

The `trialUntil` method allows you to provide a `Carbon` instance to specify when the trial period should end:

```php
use Carbon\Carbon;

$user->newSubscription('main', 'monthly')
            ->trialUntil(Carbon::now()->addDays(10))
            ->create();
```

You may determine if the user is within their trial period using either the `onTrial` method of the user instance, or the `onTrial` method of the subscription instance. The two examples below are identical:

```php
if ($user->onTrial('main')) {
    //
}

if ($user->subscription('main')->onTrial()) {
    //
}
```

#### Without Mandate Up Front

If you would like to offer trial periods without collecting the user's payment method information up front, you may set the `trial_ends_at` column on the user record to your desired trial ending date. This is typically done during user registration:

```php
$user = User::create([
    // Populate other user properties...
    'trial_ends_at' => now()->addDays(10),
]);
```

> {note}  Be sure to add a [date mutator](https://laravel.com/docs/eloquent-mutators#date-mutators) for `trial_ends_at` to your model definition.

Cashier refers to this type of trial as a "generic trial", since it is not attached to any existing subscription. The `onTrial` method on the `User` instance will return `true` if the current date is not past the value of `trial_ends_at`:

```php
if ($user->onTrial()) {
    // User is within their trial period...
}
```

You may also use the `onGenericTrial` method if you wish to know specifically that the user is within their "generic" trial period and has not created an actual subscription yet:

```php
if ($user->onGenericTrial()) {
    // User is within their "generic" trial period...
}
```

Once you are ready to create an actual subscription for the user, you may use the `newSubscription` method as usual:

```php
$user = User::find(1);

$user->newSubscription('main', 'monthly')->create();
```
    
### Defining Webhook Event Handlers

Cashier automatically handles subscription cancellation on failed charges.

Additionally, listen for the following events (in the `Laravel\Cashier\Events` namespace) to add app specific behaviour:
- `OrderPaymentPaid` and `OrderPaymentFailed`
- `FirstPaymentPaid` and `FirstPaymentFailed`

### One-off charges

Coming soon.

### Invoices

Listen for the `OrderInvoiceAvailable` event (in the `Laravel\Cashier\Events` namespace).
When a new order has been processed, you can grab the invoice by
    
```php
$invoice = $event->order->invoice();
$invoice->view(); // get a Blade view
$invoice->pdf(); // get a pdf of the Blade view
$invoice->download(); // get a download response for the pdf
```

To list invoices, access the user's orders using: `$user->orders->invoices()`.
This includes invoices for all orders, even unprocessed or failed orders.

For list of invoices

```php
<ul class="list-unstyled">
    @foreach(auth()->user()->orders as $order)
    <li>
        
        <a href="/download-invoice/{{ $order->id }}">
            {{ $order->invoice()->id() }} -  {{ $order->invoice()->date() }}
        </a>
    </li>
    @endforeach
</ul>
```
and add this route inside web.php

```php
Route::middleware('auth')->get('/download-invoice/{orderId}', function($orderId){

    return (request()->user()->downloadInvoice($orderId));
});
```

### Refunding Charges

Coming soon. 

### Customer balance

In some cases (i.e. when swapping to a cheaper plan), a customer can have overpaid. The amount that has been overpaid
will be added to the customer balance.

The customer balance is automatically processed in each Order.

A separate balance is kept for each currency.

There are a few methods available to interact with the balance directly.
__Use these with care:__

```php
$credit = $user->credit('EUR');
$user->addCredit(money(10, 'EUR')); // add €10.00
$user->hasCredit('EUR');
```

When an Order with a negative total amount due is processed, that amount is credited to the user balance.
A `BalanceTurnedStale` event will be raised if the user has no active subscriptions at that moment.
Listen for this event if you'd like to refund the remaining balance and/or want to notify the user.

### Customer locale

Mollie provides a checkout tailored to the customer's locale. For this it guesses the visitor's locale. To override the
default locale, configure it in `config/cashier.php`. This is convenient for servicing a single country.

If you're dealing with multiple locales and want to override Mollie's default behaviour, implement the `getLocale()`
method on the billable model. A common way is to add a nullable `locale` field to the user table and retrieve its value:

```php
class User extends Model
{
    /**
     * @return string
     * @link https://docs.mollie.com/reference/v2/payments-api/create-payment#parameters
     * @example 'nl_NL'
     */
    public function getLocale() {
        return $this->locale; 
    }
}
```

### All Cashier Events

You can listen for the following events from the Laravel\Cashier\Events namespace:

#### `BalanceTurnedStale` event
The user has a positive account balance, but no active subscriptions. Consider a refund.

#### `CouponApplied` event
A coupon was applied to an OrderItem. Note the distinction between _redeeming_ a coupon and _applying_ a coupon. A
redeemed coupon can be applied to multiple orders. I.e. applying a 6 month discount on a monthly subscription using a
single (redeemed) coupon.

#### `FirstPaymentFailed` event
The first payment (used for obtaining a mandate) has failed.

#### `FirstPaymentPaid` event
The first payment (used for obtaining a mandate) was successful.

#### `MandateClearedFromBillable` event
The `mollie_mandate_id` was cleared on the billable model. This happens when a payment has failed because of a invalid
mandate.

#### `MandateUpdated` event
The billable model's mandate was updated. This usually means a new payment card was registered.

#### `OrderCreated` event
An Order was created.

#### `OrderInvoiceAvailable` event
An Invoice is available on the Order. Access it using `$event->order->invoice()`.

#### `OrderPaymentFailed` event
The payment for an order has failed.

#### `OrderPaymentPaid` event
The payment for an order was successful. 

#### `OrderProcessed` event
The order has been fully processed.

#### `SubscriptionStarted` event
A new subscription was started.

#### `SubscriptionCancelled` event
The subscription was cancelled.

#### `SubscriptionResumed` event
The subscription was resumed.

#### `SubscriptionPlanSwapped` event
The subscription plan was swapped.

#### `SubscriptionQuantityUpdated` event
The subscription quantity was updated.

## Metered billing with variable amounts

Some business cases will require dynamic subscription amounts.

To allow for full flexibility Cashier Mollie allows you to define your own set of Subscription OrderItem preprocessors.
These preprocessors are invoked when the OrderItem is due, right before being processed into a Mollie payment.

If you're using metered billing, this is a convenient place to calculate the amount based on the usage statistics and
reset any counters for the next billing cycle.

You can define the preprocessors in the `cashier_plans` config file.

## Ok. So how does this all actually work?

This Cashier implementation schedules triggering payments from the client side, instead of relying on subscription management at Mollie.
(Yes, Mollie also offers a Subscription API, but it does not support all of Cashier features, so this package provides its own subscription engine.)  

From a high level perspective, this is what the process looks like:

1. A `Subscription` is created using the `MandatePaymentSubscriptionBuilder` (redirecting to Mollie's checkout to create
a `Mandate`) or `PremandatedSubscriptionBuilder` (using an existing `Mandate`).
2. The `Subscription` yields a scheduled `OrderItem` at the beginning of each billing cycle.
3. `OrderItems` which are due are preprocessed and bundled into `Orders` whenever possible by a scheduled job (i.e.
daily). This is done so your customer will receive a single payment/invoice for multiple items later on in the chain).
Preprocessing the `OrderItems` may involve applying dynamic discounts or metered billing, depending on your
configuration.
4. The `Order` is processed by the same scheduled job into a payment:
    - First, (if available) the customer's balance is processed in the `Order`.
    - If the total due is positive, a Mollie payment is incurred.
    - If the total due is 0, nothing happens.
    - If the total due is negative, the amount is added to the user's balance. If the user has no active subscriptions left, the `BalanceTurnedStale` event will be raised.
5. You can generate an `Invoice` (html/pdf) for the user.

## F.A.Q. - Frequently Asked Questions

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

<details>
<summary>Example Plan model (app/Plan.php) with buildCashierPlan and returns a \Laravel\Cashier\Plan\Plan</summary>

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
</details>

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

## Testing

Cashier Mollie is tested against Mollie's test API.

Start with copying `phpunit.xml.dist` into `phpunit.xml`, and set these environment variables in `phpunit.xml`:

**Mollie API test key**
You can obtain this key from the dashboard right after signing up.

```xml
<env name="MOLLIE_KEY" value="YOUR_VALUE_HERE"/>
```

**ID of a customer with a valid directdebit mandate** 
```xml
<env name="MANDATED_CUSTOMER_DIRECTDEBIT" value="YOUR_VALUE_HERE"/>
```

**Mandate's ID (of the previously mentioned customer)**
```xml
<env name="MANDATED_CUSTOMER_DIRECTDEBIT_MANDATE_ID" value="YOUR_VALUE_HERE"/>
```

**ID of a successful ("paid) payment by the customer**
Use a 1000 EUR amount. 
```xml
<env name="PAYMENT_PAID_ID" value="YOUR_VALUE_HERE"/>
```

**ID of an unsuccessful ("failed") payment by the customer**
```xml
<env name="PAYMENT_FAILED_ID" value="YOUR_VALUE_HERE"/>
```

Now you can run:

``` bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email support@mollie.com instead of using the issue tracker.

## Credits

- [Mollie.com](https://www.mollie.com)
- [Sander van Hooft](https://github.com/sandervanhooft)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
