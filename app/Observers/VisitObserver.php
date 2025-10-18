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
        Log::info("VisitObserver: update triggered for visit {$visit->id}");

        $hasDateOrTimeChanged =
            $visit->wasChanged('date') ||
            $visit->wasChanged('start_time') ||
            $visit->wasChanged('end_time') ||
            $visit->wasChanged('doctor_id');

        if ($hasDateOrTimeChanged) {
            Log::info("Visit {$visit->id} time or doctor changed — updating slots...");

            // Utwórz obiekt Visit z oryginalnymi danymi (przed zmianą)
            $originalVisit = new Visit([
                'doctor_id'  => $visit->getOriginal('doctor_id'),
                'date'       => $visit->getOriginal('date'),
                'start_time' => $visit->getOriginal('start_time'),
                'end_time'   => $visit->getOriginal('end_time'),
            ]);
            $originalVisit->id = $visit->id;

            // Zwolnij stare sloty
            Log::info("Releasing old slots for visit {$visit->id}");
            $this->service->markAvailable($originalVisit);

            // Zajmij nowe sloty
            Log::info("Marking new reserved slots for visit {$visit->id}");
            $this->service->markReserved($visit);
        } else {
            Log::info("Visit {$visit->id} updated, but no date/time change detected — skipping slot update.");
        }
    }
}
