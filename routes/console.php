<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('compareData', function () {
    
        echo '\nSchedule job every min in handle\n';
        $BcUpdateLocalDbController = app(\App\Http\Controllers\BcUpdateLocalDbController::class);
    
        $result= $BcUpdateLocalDbController->compareData();

})->purpose('updating db with Nav');
