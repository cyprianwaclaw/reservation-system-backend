<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DoctorSlot;
use Carbon\Carbon;

class DoctorSlotExtendSeeder extends Seeder
{
    public function run(): void
    {
        // ILE DNIÓW CHCESZ DODAĆ (zmieniasz jak chcesz)
        $daysToAdd = 10;

        // Pobieramy wszystkich lekarzy, którzy mają jakiekolwiek sloty
        $doctorIds = DoctorSlot::distinct()->pluck('doctor_id');

        foreach ($doctorIds as $doctorId) {

            // Ostatnia istniejąca data slotów
            $lastDate = DoctorSlot::where('doctor_id', $doctorId)
                ->orderByDesc('date')
                ->value('date');

            if (!$lastDate) {
                // Jeżeli lekarz nie ma żadnych slotów — pomijamy
                continue;
            }

            $current = Carbon::parse($lastDate)->copy()->addDay();

            // Generujemy kolejne X dni
            for ($i = 0; $i < $daysToAdd; $i++) {

                $this->createSlotsForDay($doctorId, $current);
                $current->addDay();
            }
        }
    }

    private function createSlotsForDay(int $doctorId, Carbon $day)
    {
        // Godziny pracy — możesz zmienić
        $slotLengthMinutes = 45;
        $start = $day->copy()->setTime(7, 30);
        $end   = $day->copy()->setTime(21, 0);

        while ($start->lt($end)) {

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
