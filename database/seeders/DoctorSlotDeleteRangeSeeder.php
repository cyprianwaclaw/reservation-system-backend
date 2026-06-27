<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DoctorSlot;

class DoctorSlotDeleteRangeSeeder extends Seeder
{
    public function run(): void
    {
        // USTAW SWÓJ ZAKRES DAT
        $from = '2024-01-16'; // od tej daty
        $to   = '2027-02-09'; // do tej daty włącznie

        DoctorSlot::whereBetween('date', [$from, $to])->delete();
    }
}