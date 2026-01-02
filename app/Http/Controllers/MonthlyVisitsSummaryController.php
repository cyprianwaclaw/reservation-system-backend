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

        $prevMonthStart = Carbon::now()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $prevMonthEnd   = Carbon::now()->subMonthNoOverflow()->endOfMonth()->endOfDay();
        $monthLabel = $prevMonthStart->translatedFormat('F Y');

        $visits = Visit::query()
            ->whereBetween('date', [$prevMonthStart, $prevMonthEnd])
            ->leftJoin('doctors', 'visits.doctor_id', '=', 'doctors.id')
            ->leftJoin('users', 'visits.user_id', '=', 'users.id')
            ->select(
                'visits.id as visit_id',
                'doctors.id as doctor_id',
                DB::raw("CONCAT(doctors.name, ' ', doctors.surname) as doctor_name"),
                'users.rodzaj_pacjenta',
                DB::raw("CONCAT(users.name, ' ', users.surname) as patient_name")
            )
            ->orderBy('doctor_name')
            ->get();

        $report = [];
        $clubSummary = [];
        $clubTypes = ['AWF', 'WKS', 'Klub gimnastyki'];

        foreach ($visits as $visit) {
            $doctorId = $visit->doctor_id ?? 'brak';
            $doctorName = $visit->doctor_name ?? 'Nieznany lekarz';
            $type = $visit->rodzaj_pacjenta ?? 'Brak';
            $patientName = $visit->patient_name ?? 'Nieznany pacjent';

            // Raport lekarza
            if (!isset($report[$doctorId])) {
                $report[$doctorId] = [
                    'doctor' => $doctorName,
                    'total' => 0,
                    'types' => [],
                ];
            }

            if (!isset($report[$doctorId]['types'][$type])) {
                $report[$doctorId]['types'][$type] = [
                    'count' => 0,
                    'patients' => [],
                ];
            }

            // Zwiększamy count dla każdej wizyty
            $report[$doctorId]['types'][$type]['count'] += 1;

            // Dodajemy pacjenta tylko jeśli jeszcze nie ma go w liście
            if (!in_array($patientName, $report[$doctorId]['types'][$type]['patients'])) {
                $report[$doctorId]['types'][$type]['patients'][] = $patientName;
            }

            $report[$doctorId]['total'] += 1;

            // Podsumowanie klubów
            if (in_array($type, $clubTypes)) {
                if (!isset($clubSummary[$type])) {
                    $clubSummary[$type] = [
                        'count' => 0,
                        'patients' => [],
                    ];
                }

                $clubSummary[$type]['count'] += 1;
                if (!in_array($patientName, $clubSummary[$type]['patients'])) {
                    $clubSummary[$type]['patients'][] = $patientName;
                }
            }
        }

        // jeśli brak wizyt
        if (empty($report)) {
            $report[] = [
                'doctor' => 'Brak wizyt w poprzednim miesiącu',
                'total' => 0,
                'types' => [],
            ];
        }

        return new MonthlyVisitsSummaryMail($report, $monthLabel, $clubSummary);
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