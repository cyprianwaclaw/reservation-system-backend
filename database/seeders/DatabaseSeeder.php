<?php

namespace Database\Seeders;

use App\Models\Doctor;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        // Najpierw lekarze
        $this->call([
            DoctorSeeder::class,
        ]);

        // Następnie godziny pracy lekarzy
        $this->call([
            DoctorWorkingHourSeeder::class,
        ]);

    }
}