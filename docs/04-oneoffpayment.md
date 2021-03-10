# One-off payment

Sometimes you may want to have your customers pay for a one-time fee.
An example of this could be a lifetime license for you product.
In this scenario you simply wish to charge your customers once, and not set up a [mandate](https://docs.mollie.com/payments/recurring) (which authorizes you to charge the client multiple times).
However, if the customer already has a valid mandate (because they are already on a subscription for example) we will use the mandate to charge the customer directly.
This means that the customer does not have to go through the payment screen again, which is user friendly.

As you may have noticed already, the payment flow described above is quite similar to the payment flow of setting up a subscription.

## Adding an item to the tab
If you wish to add something to the next upcoming order (or invoice for that matter),
you can use the `tab` method on the model that is using the `Billable` trait.

From here on we take the `$user` as our `Billable` model.

> **Important note:**
>
> You can put items on the tab without having a valid mandate for the user.
> - When creating the invoice we will create a `RedirectToCheckoutResponse` if your customer doesn't have a valid mandate.
> - When the user you are creating an order for already has a valid mandate, we will directly create the order.
    The basic usage is as follows:

```php
// Here 1000 is the amount in the smallest denomination for your default currency.
$orderItem = $user->tab('A description of the order item', 1000);
```

If you wish to override any specific fields on the order item you can pass them as the third parameters:

```php
$orderItem = $user->tab('A potato, not very premium', 100, [
    'currency' => 'USD', // a non-default currency
    'description_extra_lines' => [
        'This potato was almost rotten... but still delicious.',
    ],
]);
```

The order items that you put on the tab can be manually invoiced [using the `invoiceTab` method](#invoicing-items-on-the-tab).
If you only wish to put one item on the tab and invoice it immediately [have a look at `invoiceFor`](#putting-one-item-on-the-tab-and-invoicing-it-immediately).

> **Important note:**
>
> Since `php artisan cashier:run` is usually scheduled to run daily, items you put on the tab (but don't immediately invoice) will be invoiced automatically.
> If this is not what you want, [change the time the order item on the tab is processed.](changing-when-the-items-on-the-tab-should-be-invoiced)
## Changing when the items on the tab should be invoiced.
Since usually you have set `php artisan cashier:run` to run daily in your `App\Console\Kernel@schedule` method, the order items you put on the tab will be invoiced today/tomorrow.
If this is not what you want, override the `process_at` property.

```php
// On the first day of the month
$user->tab('Lifetime license for Product A', 35000, [
    'process_at' => now()->endOfMonth()->startOfDay(),
]);
// On the second day of the month
$user->tab('Lifetime license for Product B', 35000, [
    'process_at' => now()->endOfMonth()->startOfDay(),
]);
```

Cashier will automatically group the open tab (order items) in one order when it runs at the last day of the month.

You now have the possibility to put multiple items on the tab, and invoice them together at a later point in time!

```php
$result = $user->invoiceFor('Something', 1000, [
    // Optional. Is the default, will be used if there is no mandate, handles payment webhook.
    'webhookUrl' => config('cashier.one_off_payment.webhook_url'),
    // Optional. Is the default, will be used by Mollie if there is no mandate, after the user has paid.
    'redirectUrl' => config('cashier.one_off_payment.redirect_url'), 
    // Optional. Default => null or empty array, which let's Mollie decide.
    'method' => config('cashier.one_off_payment.active_payment_methods'),
    // Optional. @see Cashier::getLocale()
    'locale' => Cashier::getLocale($this),
    // Optional. Description on the payment. Will be shown on the bank statement.
    // Default => $items->pluck('description')->implode(', )
    'description' => 'An alternative payment description than "Something"',
]);
```

## Invoicing items on the tab
If you wish to try to invoice open items on the tab immediately, you can use the `invoiceTab` method.
Calling the invoiceTab method will collect all open order items (the open tab) that are due for processing and puts them on an order.
If you leave the currency unspecified it will try to invoice the default currency.

### Inspecting the current open tab

To check what the order (+ invoice) would look like of you were to call the `invoiceTab` method now, you can use the `upcomingInvoiceTab` method.

```php
// Simple, default currency.
$order = $user->upcomingInvoiceTab();
$invoice = $order->invoiceTab('concept', now());
// Other currency than default.
$order = $user->upcomingInvoiceTab(['currency' => 'USD']);
```

If you like what you see, you can call the `invoiceTab` method.

```php
$result = $user->invoiceTab();
```

### Invoicing the items in default currency on the tab
If you wish to invoice the items in the default currency simply call the `invoiceTab` method.

```php
$result = $user->invoiceTab(['description' => 'Something that will get on invoice & user bank records.']);
if ($result === false) {
    // There was no open tab due for processing in the currency you tried to invoice.
} elseif (is_a($result, RedirectRedirectToCheckoutResponse::class)) {
    // The user has an open tab, redirect to Mollie to let them pay for it.
    // After the user successfully paid, we will create an Order for it.
    return $result;
} elseif (is_a($result, Order::class)) {
    // The user had an open tab and a valid mandate.
    // We used the mandate to invoice them, and create an Order (+ Invoice).
}
```

### Invoicing the items in a different currency than the default
If you wish to invoice the items on the tab that are not in the default currency, you can pass the currency.
The rest of the steps are the same as invoicing in the default currency.

```php
$invoice = $user->invoiceTab(['currency' => 'USD']);
```

## Putting one item on the tab and invoicing it immediately
If you wish to put one item on the tab and invoice it immediately, you can use the `invoiceFor` method.
This is basically a shortcut for using `tab` and `invoiceTab` consecutively.

If something like the following is your scenario:
```php
$orderItem = $user->tab('Premium item', 30000, [
    'currency' => 'USD',
    'description_extra_lines' => [
        'Extra line',
        'Another line'
    ],
]);
$result = $user->invoiceTab([
    'currency' => 'USD',
    // Note: the description will be visible on the invoice & customer bank records.
    'description' => 'Lifetime license for my product',
]);
// Do the invoice handling like documented at the `invoiceTab` method.
```

You may rewrite this as:

```php
$result = $user->invoiceFor(
    'Premium item',
    30000,
    // Order item options (same as 3rd parameter of `tab`)
    [
        'currency' => 'USD',
        'description_extra_lines' => [
            'Extra line',
            'Another line'
        ],
    ],
    // Invoice options (same as 1st parameter of `invoiceTab`)
    [
       'description' => 'Lifetime license for my product',
    ]
);
// Do the invoice handling like documented at the `invoiceTab` method.
```
