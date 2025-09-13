<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Doctor;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
