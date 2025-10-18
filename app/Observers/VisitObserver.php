<?php

// namespace App\Observers;

// use App\Models\Visit;
// use App\Services\DoctorSlotService;
// use Illuminate\Support\Facades\Log;

// class VisitObserver
// {
//     protected DoctorSlotService $service;

//     public function __construct(DoctorSlotService $service)
//     {
//         $this->service = $service;
//     }

//     // public function created(Visit $visit): void
//     // {
//     //     $this->service->markReserved($visit);
//     // }
//     public function created(Visit $visit): void
//     {
//         Log::info('VisitObserver triggered for visit: ' . $visit->id);
//         $this->service->markReserved($visit);
//     }
//     public function deleted(Visit $visit): void
//     {
//         $this->service->markAvailable($visit);
//     }
// }<?php

namespace App\Observers;

use App\Models\Visit;
use App\Services\DoctorSlotService;
use Illuminate\Support\Facades\Log;

class VisitObserver
{
    protected DoctorSlotService $service;

    public function __construct(DoctorSlotService $service)
    {
        $this->service = $service;
    }

    public function created(Visit $visit): void
    {
        Log::info('VisitObserver triggered for visit: ' . $visit->id);
        $this->service->markReserved($visit);
    }

    public function deleted(Visit $visit): void
    {
        Log::info('VisitObserver triggered for deletion of visit: ' . $visit->id);
        $this->service->markAvailable($visit);
    }
  public function updated(Visit $visit): void
    {
        Log::info("VisitObserver updated for visit {$visit->id}");

        // Sprawdzamy, czy zmieniono kluczowe pola
        if (
            $visit->isDirty(['date', 'start_time', 'end_time', 'doctor_id'])
        ) {
            Log::info("Visit {$visit->id} changed — updating slots.");

            // 1️⃣ Najpierw zwolnij poprzednie sloty (stare wartości)
            $original = (object)[
                'doctor_id'  => $visit->getOriginal('doctor_id'),
                'date'       => $visit->getOriginal('date'),
                'start_time' => $visit->getOriginal('start_time'),
                'end_time'   => $visit->getOriginal('end_time'),
                'id'         => $visit->id,
            ];
            $this->service->markAvailable($original);

            // 2️⃣ Następnie zarezerwuj nowe
            $this->service->markReserved($visit);
        }
    }
}