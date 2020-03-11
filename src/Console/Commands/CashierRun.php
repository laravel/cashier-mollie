<?php

namespace Laravel\Cashier\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;

class CashierRun extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashier:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process due order items';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $orders = Cashier::run();
        $this->info('Created ' . $orders->count() . ' orders.');
    }
}
