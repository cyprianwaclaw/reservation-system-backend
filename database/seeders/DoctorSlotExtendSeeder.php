<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DoctorSlot;
use Carbon\Carbon;

class DoctorSlotExtendSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------------------------
        // USTAW SWÓJ ZAKRES DAT
        // ---------------------------------
        $from = Carbon::parse('2026-01-16');
        $to   = Carbon::parse('2026-02-09');

        // Pobieramy wszystkich lekarzy
        $doctorIds = DoctorSlot::distinct()->pluck('doctor_id');

        foreach ($doctorIds as $doctorId) {

            $currentDay = $from->copy();

            while ($currentDay->lte($to)) {

                // (opcjonalnie) pomiń weekendy
                // if ($currentDay->isWeekend()) {
                //     $currentDay->addDay();
                //     continue;
                // }

                // Zabezpieczenie przed duplikacją
                $exists = DoctorSlot::where('doctor_id', $doctorId)
                    ->where('date', $currentDay->toDateString())
                    ->exists();

                if (! $exists) {
                    $this->createSlotsForDay($doctorId, $currentDay);
                }

                $currentDay->addDay();
            }
        }
    }

    private function createSlotsForDay(int $doctorId, Carbon $day)
    {
        $slotLengthMinutes = 45;

        $start = $day->copy()->setTime(7, 30);
        $end   = $day->copy()->setTime(21, 0);

        while ($start->copy()->addMinutes($slotLengthMinutes)->lte($end)) {

            DoctorSlot::create([
                'doctor_id'  => $doctorId,
                'date'       => $day->toDateString(),
                'start_time' => $start->format('H:i:s'),
                'end_time'   => $start->copy()->addMinutes($slotLengthMinutes)->format('H:i:s'),
            ]);

            $start->addMinutes($slotLengthMinutes);
        }
    }
}
