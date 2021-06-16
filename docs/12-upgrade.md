# Upgrade Guide
##Upgrading To 2.0 From 1.x

```bash
composer require laravel/cashier-mollie "^2.0"
```

Once you have pulled in the package:

1. Run `php artisan cashier:update` (Optional, for safety update, you can use `php artisan cashier:update --maintenance`. That will put the application in maintenance mode while the update process is in progress, lock webhooks and cashier::run) 
2. After that, remove cashier_backup_orders table, if everything is ok.

3. Use `quantity` in `AddBalance` and `AddGenericOrderItem`

- Find `AddGenericOrderItem::create([...])` usage and add `$quantity`. The actual constructor is
```php
public function __construct(Model $owner, Money $unitPrice, int $quantity, string $description, int $roundingMode = Money::ROUND_HALF_UP) {...}
```
- Find `AddBalance::create([...])` usage and add `$quantity`. The actual constructor is
```php
public function __construct(Model $owner, Money $subtotal, int $quantity, string $description) {...}
```
