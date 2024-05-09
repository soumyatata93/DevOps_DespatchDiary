<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class APIRequestCall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:call';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Artisan command to send api call';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        echo "\n=====================================\n";
        echo '\nSchedule job every min in handle\n';
        $BcUpdateLocalDbController = app(\App\Http\Controllers\BcUpdateLocalDbController::class);
    
        $result= $BcUpdateLocalDbController->compareData();
    }
}
