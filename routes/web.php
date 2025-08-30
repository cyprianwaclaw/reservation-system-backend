<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Mail\VisitReminderMail;
use App\Mail\VisitConfirmationMail;
use App\Models\Visit;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/run-reminders', function () {
    Artisan::call('visits:check-tomorrow');
    return 'Scheduler executed';
});
Route::get('/remainder-mail', function () {
    $visit = Visit::find(2); // przykładowa wizyta
    return new VisitReminderMail($visit);
});
Route::get('/confirmation-mail', function () {
    $visit = Visit::find(2); // przykładowa wizyta
    return new VisitConfirmationMail($visit);
});