<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Services\DoctorSlotService;
use Carbon\Carbon;

class DoctorSlotSeeder extends Seeder
{
    public function run(): void
    {
        $service = new DoctorSlotService();

        $doctorIds = [1, 2, 4, 5];

        foreach ($doctorIds as $doctorId) {
            $service->generateSlots(
                doctorId: $doctorId,
                from: Carbon::now(),
                to: Carbon::now()->addDays(50),
                slotLengthMinutes: 45
            );
        }
    }
}
