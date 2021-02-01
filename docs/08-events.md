# Cashier Events

You can listen for the following events from the Laravel\Cashier\Events namespace:

### `BalanceTurnedStale` event
The user has a positive account balance, but no active subscriptions. Consider a refund.

### `CouponApplied` event
A coupon was applied to an OrderItem. Note the distinction between _redeeming_ a coupon and _applying_ a coupon. A
redeemed coupon can be applied to multiple orders. I.e. applying a 6 month discount on a monthly subscription using a
single (redeemed) coupon.

### `FirstPaymentFailed` event
The first payment (used for obtaining a mandate) has failed.

### `FirstPaymentPaid` event
The first payment (used for obtaining a mandate) was successful.

### `MandateClearedFromBillable` event
The `mollie_mandate_id` was cleared on the billable model. This happens when a payment has failed because of a invalid
mandate.

### `MandateUpdated` event
The billable model's mandate was updated. This usually means a new payment card was registered.

### `OrderCreated` event
An Order was created.

### `OrderInvoiceAvailable` event
An Invoice is available on the Order. Access it using `$event->order->invoice()`.

### `OrderPaymentFailed` event
The payment for an order has failed.

### `OrderPaymentFailedDueToInvalidMandate` event
The payment for an order has failed due to an invalid payment mandate. This happens for example when the customer's credit card has expired.

### `OrderPaymentPaid` event
The payment for an order was successful.

### `OrderProcessed` event
The order has been fully processed.

### `SubscriptionStarted` event
A new subscription was started.

### `SubscriptionCancelled` event
The subscription was cancelled.

### `SubscriptionResumed` event
The subscription was resumed.

### `SubscriptionPlanSwapped` event
The subscription plan was swapped.

### `SubscriptionQuantityUpdated` event
The subscription quantity was updated.
