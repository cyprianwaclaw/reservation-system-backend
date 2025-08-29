<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Visit;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckTomorrowVisits extends Command
{
    protected $signature = 'visits:check-tomorrow';
    protected $description = 'Codziennie rano o 9:00 pokazuje wizyty zaplanowane na jutro';

    public function handle()
    {
        $tomorrow = Carbon::tomorrow();

        Log::info("[CheckTomorrowVisits] Sprawdzanie wizyt na {$tomorrow->format('d.m.Y')}");

        $visits = Visit::whereDate('date', $tomorrow->toDateString())->get();

        if ($visits->isEmpty()) {
            Log::info("[CheckTomorrowVisits] Brak wizyt na jutro.");
            $this->info('Brak wizyt na jutro.');
            return;
        }

        foreach ($visits as $visit) {
            Log::info("[CheckTomorrowVisits] Wizyta ID {$visit->id}, użytkownik: {$visit->user->email}, data: {$visit->date}");
            $this->info("Wizyta ID {$visit->id}, użytkownik: {$visit->user->email}, data: {$visit->date}");
        }
    }
}
