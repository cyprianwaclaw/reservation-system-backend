<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DoctorWorkingHour;

class DoctorWorkingHourSeeder extends Seeder
{
    public function run(): void
    {
        // Usuń wszystkie istniejące godziny pracy
        DoctorWorkingHour::truncate();

        $doctors = [1, 2, 4, 5];
        $days = [1, 2, 3, 4, 5]; // poniedziałek-piątek

        foreach ($doctors as $doctorId) {
            foreach ($days as $day) {
                DoctorWorkingHour::create([
                    'doctor_id'   => $doctorId,
                    'day_of_week' => $day,
                    'start_time'  => '07:30:00',
                    'end_time'    => '18:45:00',
                ]);
            }
        }
    }
}