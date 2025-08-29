<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/run-reminders', function () {
    Artisan::call('visits:check-tomorrow');
    return 'Scheduler executed';
});
