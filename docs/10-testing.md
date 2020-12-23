# Testing

Cashier Mollie is tested against Mollie's test API.

Start with copying `phpunit.xml.dist` into `phpunit.xml`, and set these environment variables in `phpunit.xml`:

**Mollie API test key**
You can obtain this key from the dashboard right after signing up.

```xml
<env name="MOLLIE_KEY" value="YOUR_VALUE_HERE"/>
```

**ID of a customer with a valid directdebit mandate**
```xml
<env name="MANDATED_CUSTOMER_DIRECTDEBIT" value="YOUR_VALUE_HERE"/>
```

**Mandate's ID (of the previously mentioned customer)**
```xml
<env name="MANDATED_CUSTOMER_DIRECTDEBIT_MANDATE_ID" value="YOUR_VALUE_HERE"/>
```

**ID of a successful ("paid) payment by the customer**
Use a 1000 EUR amount.
```xml
<env name="PAYMENT_PAID_ID" value="YOUR_VALUE_HERE"/>
```

**ID of an unsuccessful ("failed") payment by the customer**
```xml
<env name="PAYMENT_FAILED_ID" value="YOUR_VALUE_HERE"/>
```

Now you can run:

``` bash
composer test
```