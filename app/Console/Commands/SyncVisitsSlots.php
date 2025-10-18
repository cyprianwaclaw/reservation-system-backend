<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Visit;
use App\Models\DoctorSlot;
use App\Services\DoctorSlotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncVisitsSlots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slots:sync-visits';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize existing visits with existing doctor slots only';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $slotService = new DoctorSlotService();

        // Pobieramy wszystkie daty, dla których istnieją sloty
        $slotDates = DoctorSlot::distinct()
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // Pobieramy wizyty tylko dla tych dat
        $visits = Visit::whereIn('date', $slotDates)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $this->info("Found {$visits->count()} visits to synchronize with existing slots.");

        foreach ($visits as $visit) {
            $doctorId = $visit->doctor_id;
            $date = Carbon::parse($visit->date)->format('Y-m-d');
            $visitStart = Carbon::parse("{$date} {$visit->start_time}");
            $visitEnd = Carbon::parse("{$date} {$visit->end_time}");

            $this->info("Processing visit ID {$visit->id} for doctor {$doctorId} on {$date}");
            Log::info("Synchronizing visit {$visit->id} for doctor {$doctorId} on {$date}");

            $slots = DoctorSlot::where('doctor_id', $doctorId)
                ->where('date', $date)
                ->where('end_time', '>', $visitStart->format('H:i:s'))
                ->where('start_time', '<', $visitEnd->format('H:i:s'))
                ->get();

            if ($slots->isNotEmpty()) {
                $slotIds = $slots->pluck('id');
                DoctorSlot::whereIn('id', $slotIds)->update([
                    'type' => 'reserved',
                    'visit_id' => $visit->id,
                ]);

                $this->info("Marked {$slots->count()} slots as reserved for visit {$visit->id}");
                Log::info("Marked {$slots->count()} slots as reserved for visit {$visit->id}");
            } else {
                $this->warn("No slots found for visit {$visit->id} on {$date}");
                Log::warning("No slots found for visit {$visit->id} on {$date}");
            }
        }

        $this->info("Synchronization of existing visits completed.");
        Log::info("Synchronization of existing visits completed.");

        return 0;
    }
}
