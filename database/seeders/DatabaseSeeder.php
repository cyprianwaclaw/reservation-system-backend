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
        // Wyczyść tabelę doctors, aby uniknąć duplikatów
        DB::table('doctors')->truncate();

        // Seeder godzin pracy lekarzy
        $this->call([
            DoctorWorkingHourSeeder::class,
        ]);

        // Dodaj lekarzy
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
