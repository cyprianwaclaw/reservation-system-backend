<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DoctorSlot;

class DoctorSlotDeleteRangeSeeder extends Seeder
{
    public function run(): void
    {
        // USTAW SWÓJ ZAKRES DAT
        $from = '2026-02-16'; // od tej daty
        $to   = '2026-02-16'; // do tej daty włącznie

        DoctorSlot::whereBetween('date', [$from, $to])->delete();
    }
}
