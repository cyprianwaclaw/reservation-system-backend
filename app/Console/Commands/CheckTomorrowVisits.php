<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Visit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\VisitReminderMail;
use App\Mail\VisitReminderFridayDoctorMail;
use Carbon\Carbon;
use Smsapi\Client\Curl\SmsapiHttpClient;
use Smsapi\Client\Feature\Sms\Bag\SendSmsBag;

class CheckTomorrowVisits extends Command
{
    /**
     * 🔧 TRYB TESTOWY
     * Podaj ID wizyty lub null
     */
    const VISIT_TEST_ID = null; // null/id

    protected $signature = 'visits:check-tomorrow';
    protected $description = 'Wysyła przypomnienia mailowe i SMS o wizytach (różne treści)';

    public function handle()
    {
        // -----------------------------------
        // TRYB TESTOWY / PRODUKCJA
        // -----------------------------------
        if (self::VISIT_TEST_ID) {
            $visits = Visit::where('id', self::VISIT_TEST_ID)->get();
            Log::info('[CheckTomorrowVisits] TRYB TESTOWY – wizyta ID: ' . self::VISIT_TEST_ID);
        } else {
            $tomorrow = Carbon::tomorrow();
            $visits = Visit::whereDate('date', $tomorrow->toDateString())->get();
            Log::info('[CheckTomorrowVisits] Sprawdzanie wizyt na ' . $tomorrow->format('d.m.Y'));
        }

        if ($visits->isEmpty()) {
            Log::info('[CheckTomorrowVisits] Brak wizyt.');
            $this->info('Brak wizyt do wysłania.');
            return;
        }

        // -----------------------------------
        // SMS API
        // -----------------------------------
        $token = env('SMSAPI_TOKEN');
        $client = new SmsapiHttpClient();
        $service = $client->smsapiPLService($token);

        foreach ($visits as $visit) {

            $user = $visit->user;

            if (!$user) {
                Log::warning("[CheckTomorrowVisits] Wizyta ID {$visit->id} bez użytkownika");
                continue;
            }

            $visitDate = Carbon::parse($visit->date);
            $visitTime = Carbon::parse($visit->date . ' ' . $visit->start_time)->format('H:i');

            $isFriday = $visitDate->isFriday();
            $isDoctorOne = $visit->doctor_id === 1;
            $isSpecial = $isFriday && $isDoctorOne;

            // -----------------------------------
            // E-MAIL
            // -----------------------------------
            if ($user->email) {
                try {
                    if ($isSpecial) {
                        Mail::to($user->email)->send(new VisitReminderFridayDoctorMail($visit));
                        Log::info('[MAIL] Specjalny → ' . $user->email);
                    } else {
                        Mail::to($user->email)->send(new VisitReminderMail($visit));
                        Log::info('[MAIL] Standardowy → ' . $user->email);
                    }
                } catch (\Throwable $e) {
                    Log::error("[CheckTomorrowVisits] Błąd maila ({$user->email}): " . $e->getMessage());
                }
            }

            // -----------------------------------
            // SMS
            // -----------------------------------
            if ($user->phone) {
                try {
                    $name = $user->name;
                    $message = $isSpecial
                        ? "Czesc $name,\nzapraszamy jutro o $visitTime.\nna wizyte w Raciborowicach\nul. Krajobrazowa 15L.\nZmiana terminu: 697703263\n\nFizjoterapia Kaczmarek"
                        : "Czesc $name,\nzapraszamy jutro o $visitTime\nna wizyte w budynku basenu AWF pietro -1\nZmiana terminu: 697703263\n\nFizjoterapia Kaczmarek";

                    $sms = SendSmsBag::withMessage(
                        '48' . preg_replace('/\D/', '', $user->phone),
                        $this->normalizeMessage($message)
                    );
                    $sms->from = 'Kaczmarek';
                    $service->smsFeature()->sendSms($sms);

                    Log::info('[SMS] ' . ($isSpecial ? 'SPECJALNY' : 'STANDARD') . ' → ' . $user->phone);
                } catch (\Throwable $e) {
                    Log::error("[CheckTomorrowVisits] Błąd SMS ({$user->phone}): " . $e->getMessage());
                }
            }

            $this->info("Powiadomienia wysłane do {$user->name}");
        }

        Log::info('[CheckTomorrowVisits] Wszystkie wizyty przetworzone.');
    }

    /**
     * Normalizacja znaków PL do SMS
     */
    private function normalizeMessage(string $text): string
    {
        return strtr($text, [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N',
            'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z',
        ]);
    }
}
