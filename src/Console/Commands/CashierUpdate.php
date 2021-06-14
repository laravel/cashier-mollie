<?php

namespace Laravel\Cashier\Console\Commands;

use DB;
use Illuminate\Console\Command;
use Laravel\Cashier\Order\Order;
use Laravel\Cashier\Payment;
use Mollie\Laravel\Facades\Mollie;
use Symfony\Component\Console\Command\LockableTrait;

class CashierUpdate extends Command
{
    use LockableTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashier:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Cashier Mollie to v2';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (app()->environment('production')) {
            $this->alert('Running in production mode.');
            if ($this->confirm('Proceed updating Cashier?')) {
                return;
            }
        }

        $this->comment('Publishing Cashier v2 migrations...');
        $this->callSilent('vendor:publish', ['--tag' => 'cashier-update']);
        sleep(2);
        //Enable lock on Cashier::run()


        $this->comment('Lock Cashier::run()');
        $this->lock(CashierRun::class);

        //Clone impacted tables with cashier_backup prefix
        $this->comment('Clone impacted tables with cashier_backup prefix');
        DB::statement('CREATE TABLE cashier_backup_orders LIKE orders');
        DB::statement('INSERT cashier_backup_orders SELECT * FROM orders');

        //Apply new structure to tables
        $this->comment('Migrate database');
        $this->callSilent('migrate');

        //Import Mollie payments, first payment actions and refunds.
        $paymentsIds = Order::all()->pluck('mollie_payment_id');
        $mollieApi = Mollie::api();

        $this->comment('Import Mollie payments, first payment actions');
        $paymentsIds->each(function ($payment) use ($mollieApi) {
            $molliePaymentData = $mollieApi->payments()->get($payment);
            $ownerType = $molliePaymentData->metadata->owner->type;
            $ownerID = $molliePaymentData->metadata->owner->id;
            $owner = $ownerType::findOrFail($ownerID);
            Payment::createFromMolliePayment($molliePaymentData, $owner);
        });

        $this->comment('Unlock Cashier::run()');
        $this->release();
        $this->info('Cashier was updated successfully.');
        $this->info('Now you can remove cashier_backup_orders table, if everything is ok.');
    }
}
