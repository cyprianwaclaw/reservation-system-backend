<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Doctor;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('visits')->truncate();
        DB::table('doctor_slots')->truncate();
        DB::table('doctor_working_hours')->truncate();
        DB::table('doctors')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        Doctor::create([
            'name' => 'Michał',
            'surname' => 'Kaczmarek',
            'phone' => '123456789',
            'email' => 'test@test.pl',
            'password' => bcrypt('haslo123'),
        ]);

        Doctor::create([
            'name' => 'Grzegorz',
            'surname' => 'Roczniak',
            'phone' => '123456789',
            'email' => 'test1@test.pl',
            'password' => bcrypt('haslo123'),
        ]);

        Doctor::create([
            'name' => 'Ola',
            'surname' => 'Test',
            'phone' => '123456789',
            'email' => 'test3@test.pl',
            'password' => bcrypt('haslo123'),
        ]);

        Doctor::create([
            'name' => 'Asia',
            'surname' => 'Jachym-Drewniak',
            'phone' => '123456789',
            'email' => 'test2@test.pl',
            'password' => bcrypt('haslo123'),
        ]);
    }
}
