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

//     public function created(Visit $visit): void
//     {
//         Log::info('VisitObserver triggered for visit: ' . $visit->id);
//         $this->service->markReserved($visit);
//     }

//     public function deleted(Visit $visit): void
//     {
//         Log::info('VisitObserver triggered for deletion of visit: ' . $visit->id);
//         $this->service->markAvailable($visit);
//     }
//     public function updated(Visit $visit): void
//     {
//         Log::info("VisitObserver: update triggered for visit {$visit->id}");

//         $hasDateOrTimeChanged =
//             $visit->wasChanged('date') ||
//             $visit->wasChanged('start_time') ||
//             $visit->wasChanged('end_time') ||
//             $visit->wasChanged('doctor_id');

//         if ($hasDateOrTimeChanged) {
//             Log::info("Visit {$visit->id} time or doctor changed — updating slots...");

//             // Utwórz obiekt Visit z oryginalnymi danymi (przed zmianą)
//             $originalVisit = new Visit([
//                 'doctor_id'  => $visit->getOriginal('doctor_id'),
//                 'date'       => $visit->getOriginal('date'),
//                 'start_time' => $visit->getOriginal('start_time'),
//                 'end_time'   => $visit->getOriginal('end_time'),
//             ]);
//             $originalVisit->id = $visit->id;

//             // Zwolnij stare sloty
//             Log::info("Releasing old slots for visit {$visit->id}");
//             $this->service->markAvailable($originalVisit);

//             // Zajmij nowe sloty
//             Log::info("Marking new reserved slots for visit {$visit->id}");
//             $this->service->markReserved($visit);
//         } else {
//             Log::info("Visit {$visit->id} updated, but no date/time change detected — skipping slot update.");
//         }
//     }
// }<?php

namespace App\Observers;

use App\Models\Visit;
use App\Services\DoctorSlotService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VisitObserver
{
    protected DoctorSlotService $service;

    public function __construct(DoctorSlotService $service)
    {
        $this->service = $service;
    }

    public function created(Visit $visit): void
    {
        Log::info("VisitObserver CREATED triggered for visit {$visit->id}");
        $this->service->markReserved($visit);
    }

    public function updated(Visit $visit): void
    {
        Log::info("VisitObserver UPDATED triggered for visit {$visit->id}");

        // Sprawdzamy, czy zmieniła się data, godzina, duration lub lekarz
        if ($visit->wasChanged(['date', 'start_time', 'duration', 'doctor_id'])) {
            Log::info("Visit {$visit->id} changed — updating slots");

            // 1️⃣ Zwolnij stare sloty (oryginalne wartości)
            $oldDate     = $visit->getOriginal('date');
            $oldStart    = $visit->getOriginal('start_time');
            $oldDuration = $visit->getOriginal('duration') ?? 45;
            $oldDoctorId = $visit->getOriginal('doctor_id');

            $oldStartCarbon = Carbon::parse("{$oldDate} {$oldStart}");
            $oldEndCarbon   = $oldStartCarbon->copy()->addMinutes($oldDuration);

            $oldVisit = new Visit([
                'doctor_id'  => $oldDoctorId,
                'date'       => $oldDate,
                'start_time' => $oldStart,
                'end_time'   => $oldEndCarbon->format('H:i:s'),
            ]);
            $oldVisit->id = $visit->id;

            Log::info("Releasing old slots for visit {$visit->id}");
            $this->service->markAvailable($oldVisit);

            // 2️⃣ Zajmij nowe sloty
            $newStartCarbon = Carbon::parse("{$visit->date} {$visit->start_time}");
            $newEndCarbon   = $newStartCarbon->copy()->addMinutes($visit->duration ?? 45);

            // Ustawiamy end_time w modelu, aby markReserved wiedział
            $visit->end_time = $newEndCarbon->format('H:i:s');

            Log::info("Marking new reserved slots for visit {$visit->id}");
            $this->service->markReserved($visit);
        }
    }

    public function deleted(Visit $visit): void
    {
        Log::info("VisitObserver DELETED triggered for visit {$visit->id}");
        $this->service->markAvailable($visit);
    }
}