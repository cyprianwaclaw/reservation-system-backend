<?php

// namespace App\Services;

// use App\Models\DoctorSlot;
// use App\Models\DoctorWorkingHour;
// use Carbon\Carbon;
// use Carbon\CarbonPeriod;
// use App\Models\Visit;
// use Illuminate\Support\Facades\Cache;

// class DoctorSlotService
// {
//     /**
//      * Generuje sloty dla lekarza w danym zakresie dat
//      */
//     public function generateSlots(int $doctorId, Carbon $from, Carbon $to, int $slotLengthMinutes = 45): void
//     {
//         $workingHours = DoctorWorkingHour::where('doctor_id', $doctorId)->get();

//         $period = CarbonPeriod::create($from, $to);
//         foreach ($period as $date) {
//             $dayOfWeek = $date->dayOfWeekIso;
//             $workingHour = $workingHours->firstWhere('day_of_week', $dayOfWeek);

//             if (!$workingHour) {
//                 continue; // lekarz nie pracuje tego dnia
//             }

//             $workStart = Carbon::parse("{$date->toDateString()} {$workingHour->start_time}");
//             $workEnd = Carbon::parse("{$date->toDateString()} {$workingHour->end_time}");

//             $slots = [];
//             $current = $workStart->copy();

//             while ($current->lt($workEnd)) {
//                 $slotEnd = $current->copy()->addMinutes($slotLengthMinutes);
//                 if ($slotEnd->gt($workEnd)) break;

//                 $slots[] = [
//                     'doctor_id' => $doctorId,
//                     'date' => $date->toDateString(),
//                     'start_time' => $current->format('H:i:s'),
//                     'end_time' => $slotEnd->format('H:i:s'),
//                     'type' => 'available',
//                     'created_at' => now(),
//                     'updated_at' => now(),
//                 ];

//                 $current->addMinutes($slotLengthMinutes);
//             }

//             // Wstaw lub zaktualizuj (unikaj duplikatów)
//             DoctorSlot::upsert($slots, ['doctor_id', 'date', 'start_time'], ['end_time', 'updated_at']);
//         }
//     }

//     /**
//      * Oznacza sloty jako zajęte
//      */
//     public function reserveSlots(int $doctorId, string $date, string $visitStart, string $visitEnd, int $visitId): void
//     {
//         DoctorSlot::where('doctor_id', $doctorId)
//             ->where('date', $date)
//             ->where('start_time', '>=', $visitStart)
//             ->where('start_time', '<', $visitEnd)
//             ->update([
//                 'type' => 'reserved',
//                 'visit_id' => $visitId,
//             ]);
//     }

//     /**
//      * Oznacza sloty jako wolne po anulowaniu wizyty
//      */
//     public function freeSlots(int $visitId): void
//     {
//         DoctorSlot::where('visit_id', $visitId)->update([
//             'type' => 'available',
//             'visit_id' => null,
//         ]);
//     }

//     /**
//      * Rezerwuje wizytę o dowolnej długości, zajmując potrzebną liczbę slotów
//      *
//      * @param int $doctorId
//      * @param string $date
//      * @param string $visitStart (H:i:s)
//      * @param int $visitMinutes
//      * @param int $visitId
//      * @throws \Exception
//      */
//     public function reserveMultiSlot(int $doctorId, string $date, string $visitStart, int $visitMinutes, int $visitId): void
//     {
//         // Pobranie wszystkich dostępnych slotów w dniu
//         $slots = DoctorSlot::where('doctor_id', $doctorId)
//             ->where('date', $date)
//             ->where('type', 'available')
//             ->orderBy('start_time')
//             ->get();

//         $neededMinutes = $visitMinutes;
//         $reservedSlots = [];

//         foreach ($slots as $slot) {
//             if ($slot->start_time >= $visitStart && $neededMinutes > 0) {
//                 $reservedSlots[] = $slot->id;
//                 $slotLength = (strtotime($slot->end_time) - strtotime($slot->start_time)) / 60;
//                 $neededMinutes -= $slotLength;
//             }
//         }

//         if ($neededMinutes > 0) {
//             throw new \Exception("Brak wolnych slotów na {$visitMinutes} minut wizyty.");
//         }

//         DoctorSlot::whereIn('id', $reservedSlots)->update([
//             'type' => 'reserved',
//             'visit_id' => $visitId
//         ]);
//     }
//     public function markReserved(Visit $visit): void
//     {
//         DoctorSlot::where('doctor_id', $visit->doctor_id)
//             ->whereDate('date', $visit->date)
//             ->where('start_time', '>=', $visit->start_time)
//             ->where('end_time', '<=', $visit->end_time)
//             ->update([
//                 'type' => 'reserved',
//                 'visit_id' => $visit->id,
//             ]);

//         $this->clearCache();
//     }

//     public function markAvailable(Visit $visit): void
//     {
//         DoctorSlot::where('doctor_id', $visit->doctor_id)
//             ->whereDate('date', $visit->date)
//             ->where('start_time', '>=', $visit->start_time)
//             ->where('end_time', '<=', $visit->end_time)
//             ->update([
//                 'type' => 'available',
//                 'visit_id' => null,
//             ]);

//         $this->clearCache();
//     }

//     private function clearCache(): void
//     {
//         Cache::flush();
//     }
// }<?php

namespace App\Services;

use App\Models\DoctorSlot;
use App\Models\DoctorWorkingHour;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Visit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DoctorSlotService
{
    /**
     * Generuje sloty dla lekarza w danym zakresie dat
     */
    public function generateSlots(int $doctorId, Carbon $from, Carbon $to, int $slotLengthMinutes = 45): void
    {
        $workingHours = DoctorWorkingHour::where('doctor_id', $doctorId)->get();

        $period = CarbonPeriod::create($from, $to);
        foreach ($period as $date) {
            $dayOfWeek = $date->dayOfWeekIso;
            $workingHour = $workingHours->firstWhere('day_of_week', $dayOfWeek);

            if (!$workingHour) continue;

            $workStart = Carbon::parse("{$date->toDateString()} {$workingHour->start_time}");
            $workEnd = Carbon::parse("{$date->toDateString()} {$workingHour->end_time}");

            $slots = [];
            $current = $workStart->copy();

            while ($current->lt($workEnd)) {
                $slotEnd = $current->copy()->addMinutes($slotLengthMinutes);
                if ($slotEnd->gt($workEnd)) break;

                $slots[] = [
                    'doctor_id' => $doctorId,
                    'date' => $date->toDateString(),
                    'start_time' => $current->format('H:i:s'),
                    'end_time' => $slotEnd->format('H:i:s'),
                    'type' => 'available',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $current->addMinutes($slotLengthMinutes);
            }

            DoctorSlot::upsert($slots, ['doctor_id', 'date', 'start_time'], ['end_time', 'updated_at']);
        }
    }

    /**
     * Oznacza wszystkie sloty nachodzące na wizytę jako zajęte
     */

    public function markReserved(Visit $visit): void
    {
        $visitStart = Carbon::parse("{$visit->date} {$visit->start_time}");
        $visitEnd = Carbon::parse("{$visit->date} {$visit->end_time}");

        Log::info("Marking reserved slots for visit {$visit->id}");
        Log::info("Visit start: {$visitStart->format('H:i:s')}, end: {$visitEnd->format('H:i:s')}");

        $slots = DoctorSlot::where('doctor_id', $visit->doctor_id)
            ->where('date', $visit->date)
            ->where('end_time', '>', $visitStart->format('H:i:s'))
            ->where('start_time', '<', $visitEnd->format('H:i:s'))
            ->get();

        Log::info("Found " . $slots->count() . " overlapping slots");

        foreach ($slots as $slot) {
            Log::info("Slot {$slot->id}: {$slot->start_time} - {$slot->end_time}, type: {$slot->type}");
        }

        $updated = DoctorSlot::whereIn('id', $slots->pluck('id'))
            ->update([
                'type' => 'reserved',
                'visit_id' => $visit->id,
            ]);

        Log::info("Updated $updated slots to reserved");

        $this->clearCache();
    }

    public function markAvailable(Visit $visit): void
    {
        $visitStart = Carbon::parse("{$visit->date} {$visit->start_time}");
        $visitEnd = Carbon::parse("{$visit->date} {$visit->end_time}");

        Log::info("Releasing slots for visit {$visit->id}");
        Log::info("Visit start: {$visitStart->format('H:i:s')}, end: {$visitEnd->format('H:i:s')}");

        $slots = DoctorSlot::where('doctor_id', $visit->doctor_id)
            ->where('date', $visit->date)
            ->where('end_time', '>', $visitStart->format('H:i:s'))
            ->where('start_time', '<', $visitEnd->format('H:i:s'))
            ->get();

        Log::info("Found " . $slots->count() . " overlapping slots to release");

        foreach ($slots as $slot) {
            Log::info("Slot {$slot->id}: {$slot->start_time} - {$slot->end_time}, type: {$slot->type}");
        }

        $updated = DoctorSlot::whereIn('id', $slots->pluck('id'))
            ->update([
                'type' => 'available',
                'visit_id' => null,
            ]);

        Log::info("Updated $updated slots to available");

        $this->clearCache();
    }
    public function markVacation(string $doctorId, string $date, string $startTime, string $endTime): void
    {
        Log::info("Marking vacation slots for doctor {$doctorId}");
        $start = Carbon::parse("{$date} {$startTime}");
        $end = Carbon::parse("{$date} {$endTime}");
        Log::info("Vacation date: {$date}, start: {$start->format('H:i:s')}, end: {$end->format('H:i:s')}");

        $slots = DoctorSlot::where('doctor_id', $doctorId)
            ->where('date', $date)
            ->where('end_time', '>', $start->format('H:i:s'))
            ->where('start_time', '<', $end->format('H:i:s'))
            ->get();

        Log::info("Found " . $slots->count() . " overlapping slots for vacation");
        foreach ($slots as $slot) {
            Log::info("Slot {$slot->id}: {$slot->start_time} - {$slot->end_time}, type: {$slot->type}");
        }

        $updated = DoctorSlot::whereIn('id', $slots->pluck('id'))
            ->update([
                'type' => 'vacation',
                'visit_id' => null, // nie wiąże się z wizytą
            ]);

        Log::info("Updated {$updated} slots to vacation");

        $this->clearCache();
    }
     private function clearCache(): void
    {
        Cache::flush();
    }
}
