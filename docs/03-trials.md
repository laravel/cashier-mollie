# Subscription Trials

## With Mandate Up Front

If you would like to offer trial periods to your customers while still collecting payment method information up front, you should use the `trialDays` method when creating your subscriptions:

```php
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
