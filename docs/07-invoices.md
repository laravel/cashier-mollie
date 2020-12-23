# Invoices

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
