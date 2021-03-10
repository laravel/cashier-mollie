<?php

namespace Laravel\Cashier\Console\Commands;

use Illuminate\Console\Command;

class CashierUpdate extends Command
{
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

        $this->info('Cashier was updated successfully.');
    }
}
