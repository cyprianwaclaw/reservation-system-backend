<?php

use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\VacationController;
use Illuminate\Support\Facades\Route;

Route::get('/available-days', [ScheduleController::class, 'getAvailableDays']);
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
