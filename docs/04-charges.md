# Charges

Sometimes you may want to have your customers pay for a one-time fee.
An example of this could be a lifetime license for you product.
In this scenario you simply wish to charge your customers once, and not set up a [mandate](https://docs.mollie.com/payments/recurring) (which authorizes you to charge the client multiple times).
However, if the customer already has a valid mandate (because they are already on a subscription for example) we will use the mandate to charge the customer directly.
This means that the customer does not have to go through the payment screen again, which is user friendly.

As you may have noticed already, the payment flow described above is quite similar to the payment flow of setting up a subscription.



```php
$user = auth()->user();

$item = new \Laravel\Cashier\Charge\ChargeItemBuilder($user);
$item->unitPrice(money(100,'EUR')); //1 EUR
$item->description('Test Item');
$chargeItem = $item->create();

$item2 = new \Laravel\Cashier\Charge\ChargeItemBuilder($user);
$item2->unitPrice(money(200,'EUR'));
$item2->description('Test Item 2');
$chargeItem2 = $item2->create();

$user->newCharge()
    ->addItem($chargeItem)
    ->addItem($chargeItem2)
    ->create();
```

If the user have a valid mandate he is charges and invoiced directly.
If the user doesen't have a validat mandate he is redirect to the Mollie checkout page for payment
