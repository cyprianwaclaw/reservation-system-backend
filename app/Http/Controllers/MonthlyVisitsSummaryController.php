<?php

namespace App\Http\Controllers;

use App\Mail\MonthlyVisitsSummaryMail;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\App;

class MonthlyVisitsSummaryController extends Controller
{
    /**
     * Podgląd maila w przeglądarce
     */
    public function preview()
    {
        App::setLocale('pl');
        Carbon::setLocale('pl');

        // Poprzedni miesiąc
        $prevMonthStart = Carbon::now()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $prevMonthEnd   = Carbon::now()->subMonthNoOverflow()->endOfMonth()->endOfDay();

        $monthLabel = $prevMonthStart->translatedFormat('F Y');

        // Pobranie wizyt z poprzedniego miesiąca
        $rows = Visit::query()
            ->whereBetween('date', [$prevMonthStart, $prevMonthEnd])
            ->leftJoin('doctors', 'visits.doctor_id', '=', 'doctors.id')
            ->leftJoin('users', 'visits.user_id', '=', 'users.id')
            ->select(
                'doctors.id as doctor_id',
                DB::raw("CONCAT(doctors.name, ' ', doctors.surname) as doctor_name"),
                DB::raw('COUNT(visits.id) as visits_count'),
                'users.rodzaj_pacjenta'
            )
            ->groupBy(
                'doctors.id',
                'doctors.name',
                'doctors.surname',
                'users.rodzaj_pacjenta'
            )
            ->orderBy('doctor_name')
            ->get();

        // Agregacja raportu
        $report = [];
        foreach ($rows as $row) {
            $doctorId = $row->doctor_id ?? 'brak';
            $doctorName = $row->doctor_name ?? 'Nieznany lekarz';
            $type = $row->rodzaj_pacjenta ?? 'Brak';

            if (!isset($report[$doctorId])) {
                $report[$doctorId] = [
                    'doctor' => $doctorName,
                    'total' => 0,
                    'types' => [],
                ];
            }

            $report[$doctorId]['total'] += $row->visits_count;
            $report[$doctorId]['types'][$type] = $row->visits_count;
        }

        // jeśli brak wizyt, ustaw pusty raport
        if (empty($report)) {
            $report[] = [
                'doctor' => 'Brak wizyt w poprzednim miesiącu',
                'total' => 0,
                'types' => [],
            ];
        }

        // zwraca mail w przeglądarce
        return new MonthlyVisitsSummaryMail($report, $monthLabel);
    }

    /**
     * Wysyłka maila do admina
     */
    public function send()
    {
        App::setLocale('pl');
        Carbon::setLocale('pl');

        $prevMonthStart = Carbon::now()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $prevMonthEnd   = Carbon::now()->subMonthNoOverflow()->endOfMonth()->endOfDay();

        $monthLabel = $prevMonthStart->translatedFormat('F Y');

        $rows = Visit::query()
            ->whereBetween('date', [$prevMonthStart, $prevMonthEnd])
            ->leftJoin('doctors', 'visits.doctor_id', '=', 'doctors.id')
            ->leftJoin('users', 'visits.user_id', '=', 'users.id')
            ->select(
                'doctors.id as doctor_id',
                DB::raw("CONCAT(doctors.name, ' ', doctors.surname) as doctor_name"),
                DB::raw('COUNT(visits.id) as visits_count'),
                'users.rodzaj_pacjenta'
            )
            ->groupBy(
                'doctors.id',
                'doctors.name',
                'doctors.surname',
                'users.rodzaj_pacjenta'
            )
            ->orderBy('doctor_name')
            ->get();

        $report = [];
        foreach ($rows as $row) {
            $doctorId = $row->doctor_id ?? 'brak';
            $doctorName = $row->doctor_name ?? 'Nieznany lekarz';
            $type = $row->rodzaj_pacjenta ?? 'Brak';

            if (!isset($report[$doctorId])) {
                $report[$doctorId] = [
                    'doctor' => $doctorName,
                    'total' => 0,
                    'types' => [],
                ];
            }

            $report[$doctorId]['total'] += $row->visits_count;
            $report[$doctorId]['types'][$type] = $row->visits_count;
        }

        if (empty($report)) {
            $report[] = [
                'doctor' => 'Brak wizyt w poprzednim miesiącu',
                'total' => 0,
                'types' => [],
            ];
        }

        Mail::to(config('mail.from.address'))
            ->send(new MonthlyVisitsSummaryMail($report, $monthLabel));

        return response()->json([
            'status' => 'wysłano',
            'month' => $monthLabel,
            'doctors' => count($report)
        ]);
    }
}
