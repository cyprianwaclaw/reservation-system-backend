<?php

namespace App\Http\Controllers;

use App\Mail\MonthlyVisitsSummaryMail;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MonthlyVisitsSummaryController extends Controller
{
    public function send()
   {
        // Poprzedni miesiąc względem dzisiaj
        $today = Carbon::now();

        $prevMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $prevMonthEnd   = $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $monthLabel = Carbon::parse($prevMonthStart)->translatedFormat('F Y');

        $rows = Visit::query()
            ->whereBetween('date', [$prevMonthStart, $prevMonthEnd])
            ->join('doctors', 'visits.doctor_id', '=', 'doctors.id')
            ->join('users', 'visits.user_id', '=', 'users.id')
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
            if (!isset($report[$row->doctor_id])) {
                $report[$row->doctor_id] = [
                    'doctor' => $row->doctor_name,
                    'total' => 0,
                    'types' => [],
                ];
            }

            $report[$row->doctor_id]['total'] += $row->visits_count;
            $report[$row->doctor_id]['types'][$row->rodzaj_pacjenta ?? 'Brak'] =
                $row->visits_count;
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
}