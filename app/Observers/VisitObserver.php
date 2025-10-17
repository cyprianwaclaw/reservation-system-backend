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
}