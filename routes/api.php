<?php

use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\VacationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DoctorSlotController;

Route::prefix('doctors/{doctorId}/slots')->group(function () {
    Route::get('/', [DoctorSlotController::class, 'index']);          // pobranie slotów
    Route::get('/generate', [DoctorSlotController::class, 'generate']); // generowanie slotów
});

Route::post('/slots/reserve', [DoctorSlotController::class, 'reserve']);
Route::post('/slots/free', [DoctorSlotController::class, 'free']);
Route::post('/slots/reserve-multi', [DoctorSlotController::class, 'reserveMulti']);
// Route::get('/slots', [DoctorSlotController::class, 'getSlots']);
Route::get('/slots/range', [DoctorSlotController::class, 'getSlotsRangeTest']);
Route::get('/slots/rangeTest', [DoctorSlotController::class, 'getSlotsRangeTest']);









// Route::prefix('availability')->group(function () {
//     Route::get('/days/{start_date?}/{days_ahead?}', [ScheduleController::class, 'getAvailableDaysNew'])
//         ->where([
//             'start_date' => '\d{4}-\d{2}-\d{2}',   // YYYY-MM-DD
//             'days_ahead' => '\d{1,2}',            // 1..60
//         ]); // [web:60][web:66]

//     Route::get('/date/{date}/doctors', [ScheduleController::class, 'getDoctorsForDate'])
//         ->where('date', '\d{4}-\d{2}-\d{2}'); // [web:60][web:66]

//     Route::get('/doctor/{doctor}/date/{date}/slots', [ScheduleController::class, 'getDoctorSlots'])
//         ->where('date', '\d{4}-\d{2}-\d{2}'); // [web:60][web:66]
// });


Route::get('/available-days', [ScheduleController::class, 'getAvailableDays']);
// Route::get('/available-days-new', [ScheduleController::class, 'getAvailableDaysNew']);
Route::post('/reserve', [ScheduleController::class, 'reserve']);
Route::put('/visits-update/{visitId}', [ScheduleController::class, 'updateVisit']); //edycja danych wizyty
Route::post('/doctor/login', [ScheduleController::class, 'loginDoctor']);
Route::get('/users/{user}',  [ScheduleController::class, 'showUser']);
Route::get('/visits/{visit}',  [ScheduleController::class, 'showVisit']);
Route::delete('/schedule/visits/{id}', [ScheduleController::class, 'cancel']); // usuwanie wizyty


Route::middleware('auth:sanctum')->group(
    function () {
        Route::prefix('schedule')->group(function () {
            Route::get('/all-visits', [ScheduleController::class, 'getAllVisits']);
            Route::get('/visits/{id}', [ScheduleController::class, 'getVisitById']);
            Route::delete('/visits/{id}', [ScheduleController::class, 'cancel']); // usuwanie wizyty
        });

        Route::prefix('vacations')->group(function () {
            Route::get('/', [VacationController::class, 'index']);    // dodanie urlopu
            Route::delete('/{id}', [VacationController::class, 'destroy']); // usunięcie urlopu
        });


        Route::put('/visits/{visitId}/update', [ScheduleController::class, 'updateVisit']); //edycja danych wizyty
        Route::post('/visits/{visitId}/notes', [ScheduleController::class, 'addNote']);
        Route::delete('/notes/{noteId}', [ScheduleController::class, 'deleteNote']);


        Route::get('/allUsers', [ScheduleController::class, 'allUsers']);
        Route::get('/all-users/{id}', [ScheduleController::class, 'userByID']);
        Route::patch('/update-patient/{id}', [ScheduleController::class, 'updateUserData']);

        Route::post('/add-patients', [ScheduleController::class, 'addPatient']);

        Route::post('/end-options', [ScheduleController::class, 'getEndOptions']);

        Route::post('/add-vacations', [ScheduleController::class, 'addVacations']);

        Route::post('/fully-available-days', [ScheduleController::class, 'getFullyAvailableDaysForDoctor']);

        Route::delete('/patient-delete/{id}', [ScheduleController::class, 'deletePatient']);

        Route::post('/doctor/set-working-hours', [ScheduleController::class, 'setDoctorWorkingHours']);
        Route::get('/doctor/working-hours', [ScheduleController::class, 'getDoctorWorkingHours']);
        Route::put('/doctor/{id}/working-hours', [ScheduleController::class, 'updateDoctorWorkingHours']);
        Route::delete('/doctor/working-hours/{id}', [ScheduleController::class, 'deleteDoctorWorkingHour']);
    }
);
