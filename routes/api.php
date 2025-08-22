<?php

use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\VacationController;
use Illuminate\Support\Facades\Route;

Route::prefix('schedule')->group(function () {
    Route::get('/all-visits', [ScheduleController::class, 'getAllVisits']);
    Route::get('/visits/{id}', [ScheduleController::class, 'getVisitById']);
    Route::get('/available-days', [ScheduleController::class, 'getAvailableDays']);
    Route::get('/free-doctors', [ScheduleController::class, 'getFreeDoctors']);
    Route::get('/free-slots', [ScheduleController::class, 'getFreeSlotsForDoctor']);
    Route::post('/reserve', [ScheduleController::class, 'reserve']);
    Route::delete('/visits/{id}', [ScheduleController::class, 'cancel']); // usuwanie wizyty
});

Route::prefix('vacations')->group(function () {
    Route::get('/', [VacationController::class, 'index']);     // lista aktualnych/potencjalnych urlopów
    Route::post('/', [VacationController::class, 'store']);    // dodanie urlopu
    Route::delete('/{id}', [VacationController::class, 'destroy']); // usunięcie urlopu
});


Route::put('/visits/{visitId}/update', [ScheduleController::class, 'updateVisit']); //edycja danych wizyty
Route::post('/visits/{visitId}/notes', [ScheduleController::class, 'addNote']);
Route::delete('/notes/{noteId}', [ScheduleController::class, 'deleteNote']);


Route::get('/allUsers', [ScheduleController::class, 'allUsers']);
Route::get('/users/{id}', [ScheduleController::class, 'userByID']);

Route::post('/add-patients', [ScheduleController::class, 'addPatient']);

Route::post('/end-options', [ScheduleController::class, 'getEndOptions']);

Route::post('/add-vacations', [ScheduleController::class, 'addVacations']);

Route::post('/fully-available-days', [ScheduleController::class, 'getFullyAvailableDaysForDoctor']);
