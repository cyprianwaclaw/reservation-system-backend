<?php

namespace App\Observers;

use App\Models\Vacation;
use App\Services\DoctorSlotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class VacationObserver
{
    protected DoctorSlotService $service;

    public function __construct(DoctorSlotService $service)
    {
        $this->service = $service;
    }

    // public function created(Vacation $vacation): void
    // {
    //     Log::info("VacationObserver triggered for doctor {$vacation->doctor_id}");
    //     Log::info("Vacation range: {$vacation->start_date} → {$vacation->end_date}");

    //     $start = Carbon::parse($vacation->start_date);
    //     $end = Carbon::parse($vacation->end_date);

    //     $startTime = $vacation->all_day ? '00:00' : $vacation->start_time;
    //     $endTime = $vacation->all_day ? '23:59' : $vacation->end_time;

    //     // Przechodzimy po wszystkich dniach urlopu
    //     for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
    //         Log::info("Marking vacation for date {$date->toDateString()} from {$startTime} to {$endTime}");

    //         $this->service->markVacation(
    //             (string)$vacation->doctor_id,
    //             $date->toDateString(),
    //             (string)$startTime,
    //             (string)$endTime
    //         );
    //     }
    // }

    // public function deleted(Vacation $vacation): void
    // {
    //     Log::info("VacationObserver delete triggered for doctor {$vacation->doctor_id}");
    //     Log::info("Vacation range: {$vacation->start_date} → {$vacation->end_date}");

    //     $start = Carbon::parse($vacation->start_date);
    //     $end = Carbon::parse($vacation->end_date);

    //     $startTime = $vacation->all_day ? '00:00' : $vacation->start_time;
    //     $endTime = $vacation->all_day ? '23:59' : $vacation->end_time;

    //     for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
    //         Log::info("Releasing vacation slots for date {$date->toDateString()} from {$startTime} to {$endTime}");

    //         $this->service->markAvailable((object)[
    //             'doctor_id' => $vacation->doctor_id,
    //             'date' => $date->toDateString(),
    //             'start_time' => $startTime,
    //             'end_time' => $endTime,
    //         ]);
    //     }
    // }
public function created(Vacation $vacation): void
{
    $start = Carbon::parse($vacation->start_date);
    $end = Carbon::parse($vacation->end_date);

    $startTime = $vacation->all_day ? '00:00' : $vacation->start_time;
    $endTime = $vacation->all_day ? '23:59' : $vacation->end_time;

    for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
        $this->service->markVacation($vacation->doctor_id, $date->toDateString(), $startTime, $endTime);
    }
}

public function deleted(Vacation $vacation): void
{
    $start = Carbon::parse($vacation->start_date);
    $end = Carbon::parse($vacation->end_date);

    $startTime = $vacation->all_day ? '00:00' : $vacation->start_time;
    $endTime = $vacation->all_day ? '23:59' : $vacation->end_time;

    for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
        $this->service->releaseVacationSlots($vacation->doctor_id, $date->toDateString(), $startTime, $endTime);
    }
}
}
