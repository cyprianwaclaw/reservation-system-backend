<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DoctorWorkingHour;

class DoctorWorkingHourSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $doctors = [1, 2, 3, 4, 5];
        $days = [1, 2, 3, 4, 5];

        foreach ($doctors as $doctorId) {
            foreach ($days as $day) {
                DoctorWorkingHour::create([
                    'doctor_id'   => $doctorId,
                    'day_of_week' => $day,
                    'start_time'  => '07:30:00',
                    'end_time'    => '21:00:00',
                ]);
            }
        }
    }
}
