<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Mail\VisitReminderMail;
use App\Mail\VisitConfirmationMail;
use App\Mail\VisitCancelledMail;
use App\Models\Visit;
use App\Mail\VisitRescheduledSimpleMail;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\MonthlyVisitsSummaryController;
use App\Http\Controllers\DoctorSlotController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/run-reminders', function () {
    Artisan::call('visits:check-tomorrow');
    return 'Scheduler executed';
});

Route::get('/slots/roll-today', [DoctorSlotController::class, 'rollTodaySlots']);

// Testowa trasa do wysyłki SMS
Route::get('/send-sms', [SmsController::class, 'sendSms']);

Route::get('/visits-type', [MonthlyVisitsSummaryController::class, 'preview']);

Route::get('/remainder-mail', function () {
    $visit = Visit::find(2); // przykładowa wizyta
    return new VisitReminderMail($visit);
});

Route::get('/confirmation-mail', function () {
    $visit = Visit::find(2); // przykładowa wizyta
    return new VisitConfirmationMail($visit);
});
Route::get('/cancel-mail', function () {
    $visit = Visit::find(2); // przykładowa wizyta
    return new VisitCancelledMail($visit);
});
Route::get('/rescheduled-mail', function () {
    $visit = Visit::find(2); // przykładowa wizyta

    if (!$visit) {
        return 'Nie znaleziono wizyty testowej.';
    }

    // kopiujemy stare dane wizyty do podglądu
    $oldVisit = clone $visit;
    $oldVisit->date = now()->subDays(3); // przykładowy stary termin
    $oldVisit->start_time = now()->subDays(3)->format('H:i');

    return new VisitRescheduledSimpleMail($oldVisit, $visit);
});