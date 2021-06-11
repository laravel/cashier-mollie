<?php

namespace Laravel\Cashier\Console\Commands;

use DB;
use Illuminate\Console\Command;

class CashierUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashier:update {--maintenance: Enable maintenance mode on update Cashier}';

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
        if ($this->option('maintenance')) {
            $this->comment('Enable maintenance mode');
            $this->callSilent('down');
        }

        $this->comment('Publishing Cashier v2 migrations...');
        $this->callSilent('vendor:publish', ['--tag' => 'cashier-update']);
        sleep(2);



        $this->comment('Clone orders table with cashier_backup prefix');
        DB::statement('CREATE TABLE cashier_backup_orders LIKE orders');
        DB::statement('INSERT cashier_backup_orders SELECT * FROM orders');

        //Apply new structure to tables
        $this->comment('Migrate database');
        $this->callSilent('migrate');

        if ($this->option('maintenance')) {
            $this->comment('Disable maintenance mode');
            $this->callSilent('up');
        }
        $this->info('Cashier was updated successfully.');
        $this->info('Now you can remove cashier_backup_orders table, if everything is ok.');
    }
}
