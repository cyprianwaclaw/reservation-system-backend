<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DoctorSlot;

class DoctorSlotDeleteRangeSeeder extends Seeder
{
    public function run(): void
    {
        // USTAW SWÓJ ZAKRES DAT
        $from = '2025-12-06'; // od tej daty
        $to   = '2026-02-28'; // do tej daty włącznie

        DoctorSlot::whereBetween('date', [$from, $to])->delete();
    }
}
