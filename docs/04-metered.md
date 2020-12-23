# Metered billing

Some business cases will require dynamic subscription amounts.

To allow for full flexibility Cashier Mollie allows you to define your own set of Subscription OrderItem preprocessors.
These preprocessors are invoked when the OrderItem is due, right before being processed into a Mollie payment.

If you're using metered billing, this is a convenient place to calculate the amount based on the usage statistics and
reset any counters for the next billing cycle.

You can define the preprocessors in the `cashier_plans` config file.

### Ok. So how does this all actually work?

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
