<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Smsapi\Client\Curl\SmsapiHttpClient;
use Smsapi\Client\Feature\Sms\Bag\SendSmsBag;

class SmsController extends Controller
{
    public function sendSms()
    {
        $token = env('SMSAPI_TOKEN');

        try {
            // Utwórz adapter Curl klienta SMSAPI v3
            $client = new SmsapiHttpClient();

            // Wybierz serwis (.com) z tokenem
            $service = $client->smsapiPLService($token);

            // Nowa wiadomość
            $sms = SendSmsBag::withMessage(
                '48881427943',
                'Czesc Michal, przypominamy o Twojej wizycie jutro o 10:15 przy al. Jana Pawla II 78.
                Fizjoterapia Kaczmarek,
                tel 697703263'
            );
            $sms->from = 'Test';

            // Wyślij SMS
            $response = $service->smsFeature()->sendSms($sms);

            return response()->json([
                'status' => 'success',
                'message' => 'SMS wysłany pomyślnie!',
                'response' => $response,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}