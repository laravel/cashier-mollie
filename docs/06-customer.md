# Customer

## Updating Customer payment mandates

The `updatePaymentMethod` method may be used to update a customer's payment method information. This method redirecting the customer to the Mollie checkout to make the payment. Amount, redirect url and description for the update payment method can be set in `cashier.php` config.

```php
$user->updatePaymentMethod()->create(); // will add the amount to the user Balance
```
or
```php
$user->updatePaymentMethod()
    ->addGenericItem() // will add the amount as an Order Item
    ->create(); 
```

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
