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
#### Finding a specific invoice

##### findInvoice
It's possible to find a specific invoice by it's order id.

```php
$user->findInvoice($orderId);
```

> If the invoice is not associated with the user you're searching for, it will throw an `UnauthorizedInvoiceAccessException`.
##### findInvoiceOrFail
If you wish to show a 404 error page whenever the invoice is not found, you may use the `findInvoiceOrFail` method on your user.
If the invoice can not be found, a `\Symfony\Component\HttpKernel\Exception\NotFoundHttpException` will be thrown.
If the invoice doesn't belong to the user, it will throw a `\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException`.
In a standard Laravel application those exceptions will be turned in a proper 404 or respectively 403 HTTP response.

```php
$user->findInvoiceOrFail($orderId);
```
