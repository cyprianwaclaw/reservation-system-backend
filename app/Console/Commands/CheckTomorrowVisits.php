<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Visit;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\VisitReminderMail;

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
            // $visitsTest =  Visit::find(2);
            // Mail::to("cyprianwaclaw@gmail.com")->send(new VisitReminderMail($visitsTest));

            // $this->info("Wysłano mail do {$visit->user->email}");
            return;
        }

        foreach ($visits as $visit) {
            Log::info("[CheckTomorrowVisits] Wysyłanie przypomnienia do {$visit->user->email} (wizyta ID {$visit->id})");

            Mail::to($visit->user->email)->send(new VisitReminderMail($visit));

            $this->info("Wysłano mail do {$visit->user->email}");
        }
    }
}
