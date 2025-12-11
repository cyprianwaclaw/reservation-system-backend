<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Visit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\VisitReminderMail;
use Carbon\Carbon;
use Smsapi\Client\Curl\SmsapiHttpClient;
use Smsapi\Client\Feature\Sms\Bag\SendSmsBag;

// class CheckTomorrowVisits extends Command
// {
//     protected $signature = 'visits:check-tomorrow';
//     protected $description = 'Codziennie rano o 9:00 pokazuje wizyty zaplanowane na jutro';

//     public function handle()
//     {
//         $tomorrow = Carbon::tomorrow();

//         Log::info("[CheckTomorrowVisits] Sprawdzanie wizyt na {$tomorrow->format('d.m.Y')}");

//         $visits = Visit::whereDate('date', $tomorrow->toDateString())->get();

//         if ($visits->isEmpty()) {
//             Log::info("[CheckTomorrowVisits] Brak wizyt na jutro.");
//             $this->info('Brak wizyt na jutro.');
//             // $visitsTest =  Visit::find(2);
//             // Mail::to("cyprianwaclaw@gmail.com")->send(new VisitReminderMail($visitsTest));

//             // $this->info("Wysłano mail do {$visit->user->email}");
//             return;
//         }

//         foreach ($visits as $visit) {
//             Log::info("[CheckTomorrowVisits] Wysyłanie przypomnienia do {$visit->user->email} (wizyta ID {$visit->id})");

//             Mail::to($visit->user->email)->send(new VisitReminderMail($visit));

//             $this->info("Wysłano mail do {$visit->user->email}");
//         }
//     }
// }
class CheckTomorrowVisits extends Command
{
    // Dodany opcjonalny parametr {visit_id?}
    protected $signature = 'visits:check-tomorrow {visit_id?}';
    protected $description = 'Wysyła przypomnienia mailowe i SMS o wizytach, domyślnie sprawdza jutro, można podać visit_id do testu';

    public function handle()
    {
        $visitId = $this->argument('visit_id');

        if ($visitId) {
            // 🔹 Pobierz konkretną wizytę po ID
            $visits = Visit::where('id', $visitId)->get();
            Log::info("[CheckTomorrowVisits] Test dla wizyty ID {$visitId}");
        } else {
            // 🔹 Domyślnie wszystkie wizyty na jutro
            $tomorrow = Carbon::tomorrow();
            $visits = Visit::whereDate('date', $tomorrow->toDateString())->get();
            Log::info("[CheckTomorrowVisits] Sprawdzanie wizyt na {$tomorrow->format('d.m.Y')}");
        }

        if ($visits->isEmpty()) {
            Log::info("[CheckTomorrowVisits] Brak wizyt do wysłania powiadomień.");
            $this->info('Brak wizyt do wysłania powiadomień.');
            return;
        }

        $token = env('SMSAPI_TOKEN');
        $client = new SmsapiHttpClient();
        $service = $client->smsapiPLService($token);

        foreach ($visits as $visit) {
            $user = $visit->user;

            if (!$user) {
                Log::warning("[CheckTomorrowVisits] Wizyta ID {$visit->id} nie ma przypisanego użytkownika!");
                continue;
            }

            // -----------------------------
            // 1️⃣ E-mail
            // -----------------------------
            if ($user->email) {
                Mail::to($user->email)->send(new VisitReminderMail($visit));
                Log::info("[CheckTomorrowVisits] Wysłano e-mail do {$user->email}");
            }

            // -----------------------------
            // 2️⃣ SMS
            // -----------------------------
            if ($user->phone) {
                try {
                    $name = $user->name;
                    $visitTime = Carbon::parse($visit->date . ' ' . $visit->start_time)->format('H:i');

                    $message = "Czesc $name,\nzapraszamy jutro o $visitTime\nna wizyte w budynku basenu AWF pietro -1\nZmiana terminu: 697703263\n\nFizjoterapia Kaczmarek";

                    $normalizedMessage = $this->normalizeMessage($message);

                    $sms = SendSmsBag::withMessage(
                        '48' . preg_replace('/\D/', '', $user->phone),
                        $normalizedMessage
                    );
                    $sms->from = 'Kaczmarek';
                    $service->smsFeature()->sendSms($sms);

                    Log::info("[CheckTomorrowVisits] Wysłano SMS do {$user->phone}");
                } catch (\Throwable $e) {
                    Log::error("[CheckTomorrowVisits] Błąd wysyłki SMS do {$user->phone}: " . $e->getMessage());
                }
            }

            $this->info("Powiadomienia wysłane do {$user->name}");
        }
    }

    private function normalizeMessage(string $text): string
    {
        $map = [
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ź' => 'z',
            'ż' => 'z',
            'Ą' => 'A',
            'Ć' => 'C',
            'Ę' => 'E',
            'Ł' => 'L',
            'Ń' => 'N',
            'Ó' => 'O',
            'Ś' => 'S',
            'Ź' => 'Z',
            'Ż' => 'Z',
        ];

        return strtr($text, $map);
    }
}
