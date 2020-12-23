# Webhook Event Handlers

Cashier automatically handles subscription cancellation on failed charges.

Additionally, listen for the following events (in the `Laravel\Cashier\Events` namespace) to add app specific behaviour:
- `OrderPaymentPaid` and `OrderPaymentFailed`
- `FirstPaymentPaid` and `FirstPaymentFailed`
