# Usage

## Creating subscriptions

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
```php
$redirect = $user->newSubscriptionViaMollieCheckout('main', 'premium')->create(); // make sure to configure the 'premium' plan in config/cashier.php
```

## Coupons

Coupon handling in Cashier Mollie is designed with full flexibility in mind.

Coupons can be defined in `config/cashier_coupons.php`.

You can provide your own coupon handler by extending
`\Cashier\Discount\BaseCouponHandler`.

Out of the box, a basic `FixedDiscountHandler` is provided.

### Redeeming a coupon for an existing subscription

For redeeming a coupon for an existing subscription, use the `redeemCoupon()` method on the billable trait:
```php
$user->redeemCoupon('your-coupon-code');
```

This will validate the coupon code and redeem it. The coupon will be applied to the upcoming Order.

Optionally, specify the subscription it should be applied to:
```php
$user->redeemCoupon('your-coupon-code', 'main');
```
By default all other active redeemed coupons for the subscription will be revoked. You can prevent this by setting the
`$revokeOtherCoupons` flag to false:
```php
$user->redeemCoupon('your-coupon-code', 'main', false);
```
## Checking subscription status

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

## Cancelled Subscription Status

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

## Changing Plans

After a user is subscribed to your application, they may occasionally want to change to a new subscription plan. To swap a user to a new subscription, pass the plan's identifier to the `swap` or `swapNextCycle` method:

```php
$user = App\User::find(1);

// Swap right now
$user->subscription('main')->swap('other-plan-id');

// Swap once the current cycle has completed
$user->subscription('main')->swapNextCycle('other-plan-id');
```

If the user is on trial, the trial period will be maintained. Also, if a "quantity" exists for the subscription, that quantity will also be maintained.

## Subscription Quantity

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

## Subscription Taxes

To specify the tax percentage a user pays on a subscription, implement the `taxPercentage` method on your billable model, and return a numeric value between 0 and 100, with no more than 2 decimal places.

```php
public function taxPercentage() {
    return 20;
}
```

The `taxPercentage` method enables you to apply a tax rate on a model-by-model basis, which may be helpful for a user base that spans multiple countries and tax rates.

### Syncing Tax Percentages

When changing the hard-coded value returned by the `taxPercentage` method, the tax settings on any existing subscriptions for the user will remain the same. If you wish to update the tax value for existing subscriptions with the returned `taxPercentage` value, you should call the `syncTaxPercentage` method on the user's subscription instance:

```php
$user->subscription('main')->syncTaxPercentage();
```

## Subscription Anchor Date

Not (yet) implemented, but you could make this work by scheduling `Cashier::run()` to only execute on a specific day of the month.

## Cancelling Subscriptions

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

## Resuming Subscriptions

If a user has cancelled their subscription and you wish to resume it, use the `resume` method. The user **must** still be on their grace period in order to resume a subscription:

    $user->subscription('main')->resume();

If the user cancels a subscription and then resumes that subscription before the subscription has fully expired, they will not be billed immediately. Instead, their subscription will be re-activated, and they will be billed on the original billing cycle.

## Updating Customer payment mandates

Coming soon.

## Subscription Trials

### With Mandate Up Front

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

## Without Mandate Up Front

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

## Defining Webhook Event Handlers

Cashier automatically handles subscription cancellation on failed charges.

Additionally, listen for the following events (in the `Laravel\Cashier\Events` namespace) to add app specific behaviour:
- `OrderPaymentPaid` and `OrderPaymentFailed`
- `FirstPaymentPaid` and `FirstPaymentFailed`

## One-off charges

Coming soon.

## Invoices

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

## Refunding Charges

Coming soon.

## Customer balance

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

## Customer locale

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