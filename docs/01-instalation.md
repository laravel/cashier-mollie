# Installation

First, make sure to add the Mollie key to your `.env` file. You can obtain an API key from the [Mollie dashboard](https://www.mollie.com/dashboard/developers/api-keys):

```dotenv
MOLLIE_KEY="test_xxxxxxxxxxx"
```

Next, pull this package in using composer:

```bash
composer require laravel/cashier-mollie "^1.0"
```

## Setup

Once you have pulled in the package:

1. Run `php artisan cashier:install`.

2. Add these fields to your billable model's migration (typically the default "create_user_table" migration):

    ```php
    $table->string('mollie_customer_id')->nullable();
    $table->string('mollie_mandate_id')->nullable();
    $table->decimal('tax_percentage', 6, 4)->default(0); // optional
    $table->dateTime('trial_ends_at')->nullable(); // optional
    $table->text('extra_billing_information')->nullable(); // optional
    ```

3. Run the migrations: `php artisan migrate`

4. Ensure you have properly configured the `MOLLIE_KEY` in your .env file. You can obtain an API key from the [Mollie dashboard](https://www.mollie.com/dashboard/developers/api-keys):

    ```dotenv
   MOLLIE_KEY="test_xxxxxxxxxxxxxxxxxxxxxx"
    ```

5. Prepare the configuration files:

    - configure at least one subscription plan in `config/cashier_plans.php`.

    - in `config/cashier_coupons.php` you can manage any coupons. By default an example coupon is enabled. Consider
      disabling it before deploying to production.

    - the base configuration is in `config/cashier`. Be careful while modifying this, in most cases you will not need
      to.

6. Prepare the billable model (typically the default Laravel User model):

    - Add the `Laravel\Cashier\Billable` trait.

    - Optionally, override the method `mollieCustomerFields()` to configure what billable model fields are stored while creating the Mollie Customer.
      Out of the box the `mollieCustomerFields()` method uses the default Laravel User model fields:

    ```php
    public function mollieCustomerFields() {
        return [
            'email' => $this->email,
            'name' => $this->name,
        ];
    }
    ```
   Learn more about storing data on the Mollie Customer [here](https://docs.mollie.com/reference/v2/customers-api/create-customer#parameters).

    - Implement ```Laravel\Cashier\Order\Contracts\ProvidesInvoiceInformation``` interface. For example:

    ```php
   /**
    * Get the receiver information for the invoice.
    * Typically includes the name and some sort of (E-mail/physical) address.
    *
    * @return array An array of strings
    */
   public function getInvoiceInformation()
   {
       return [$this->name, $this->email];
   }

   /**
    * Get additional information to be displayed on the invoice. Typically a note provided by the customer.
    *
    * @return string|null
    */
   public function getExtraBillingInformation()
   {
       return null;
   }
    ```

7. Schedule a periodic job to execute `Cashier::run()`.

    ```php
    $schedule->command('cashier:run')
        ->daily() // run as often as you like (Daily, monthly, every minute, ...)
        ->withoutOverlapping(); // make sure to include this
    ```

You can find more about scheduling jobs using Laravel [here](https://laravel.com/docs/scheduling).

🎉 You're now good to go :).
