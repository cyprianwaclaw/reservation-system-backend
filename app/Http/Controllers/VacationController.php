<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vacation;
use Carbon\Carbon;

use Illuminate\Validation\ValidationException;
use App\Models\DoctorWorkingHour;
use Illuminate\Support\Collection;
use Carbon\CarbonPeriod;


class VacationController extends Controller
{


public function index(Request $request)
{
    $week = $request->query('week');
    $doctorId = $request->query('doctor_id');

    if (!$doctorId) {
        return response()->json(['message' => 'doctor_id is required for availability calculation'], 400);
    }

    // Parsujemy zakres dat
    if (!$week) {
        return response()->json(['message' => 'week query is required (e.g. 10.12.2025 - 16.12.2025)'], 400);
    }

    $dates = explode('-', $week);
    if (count($dates) !== 2) {
        return response()->json(['message' => 'week format invalid'], 400);
    }

    try {
        $startDate = Carbon::createFromFormat('d.m.Y', trim($dates[0]))->startOfDay();
        $endDate = Carbon::createFromFormat('d.m.Y', trim($dates[1]))->endOfDay();
    } catch (\Exception $e) {
        return response()->json(['message' => 'invalid date format, use d.m.Y'], 400);
    }

    // Pobieramy urlopy lekarza które mają overlap z zakresem
    $vacations = Vacation::where('doctor_id', $doctorId)
        ->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
              ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_date', '<=', $startDate->toDateString())
                     ->where('end_date', '>=', $endDate->toDateString());
              });
        })
        ->get();

    // Pobieramy godziny pracy lekarza (wszystkie wpisy)
    $workingHours = DoctorWorkingHour::where('doctor_id', $doctorId)->get()->keyBy(function($w){
        return (int)$w->day_of_week; // 1..7
    });

    // Kolekcja przedziałów (carbon start, carbon end)
    $intervals = collect();

    // 1) Dodajemy rzeczywiste urlopy (rozbijamy wielodniowe na dni z godzinami)
    foreach ($vacations as $vac) {
        // Zakładam, że pola start_date/end_date to date strings, start_time/end_time to time strings lub null
        $vacStart = Carbon::parse($vac->start_date . ' ' . ($vac->start_time ?? '00:00:00'));
        $vacEnd = Carbon::parse($vac->end_date . ' ' . ($vac->end_time ?? '23:59:59'));

        // Ograniczamy do zapytanego zakresu
        $segStart = $vacStart->greaterThan($startDate) ? $vacStart->copy() : $startDate->copy();
        $segEnd = $vacEnd->lessThan($endDate) ? $vacEnd->copy() : $endDate->copy();

        if ($segStart->lte($segEnd)) {
            $intervals->push([
                'start' => $segStart->copy(),
                'end' => $segEnd->copy(),
                'source' => 'vacation',
                'vacation_id' => $vac->id,
            ]);
        }
    }

    // 2) Dla każdego dnia z zakresu generujemy bloki poza godzinami pracy
    $period = CarbonPeriod::create($startDate->toDateString(), $endDate->toDateString());
    foreach ($period as $day) {
        $dayStart = $day->copy()->startOfDay(); // 00:00
        $dayEnd = $day->copy()->endOfDay();     // 23:59:59
        $dow = $day->dayOfWeekIso; // 1..7

        $working = $workingHours->get($dow); // może być null

        if (!$working) {
            // lekarz nie pracuje -> cały dzień unavailable
            $intervals->push([
                'start' => $dayStart->copy(),
                'end' => $dayEnd->copy(),
                'source' => 'working_absence',
                'note' => 'no_working_hours'
            ]);
            continue;
        }

        // Normalizujemy czasy pracy (baza ma format HH:MM:SS)
        $workStart = Carbon::parse($day->toDateString() . ' ' . $working->start_time);
        $workEnd = Carbon::parse($day->toDateString() . ' ' . $working->end_time);

        // Jeśli pracuje od i do tego samego czasu (równe) - traktujemy jako brak pracy
        if ($workStart->eq($workEnd)) {
            $intervals->push([
                'start' => $dayStart->copy(),
                'end' => $dayEnd->copy(),
                'source' => 'working_absence',
                'note' => 'start_equals_end'
            ]);
            continue;
        }

        // Jeśli godziny pracy nie pokrywają całego dnia - dodajemy blok przed i po
        if ($dayStart->lt($workStart)) {
            $intervals->push([
                'start' => $dayStart->copy(),
                'end' => $workStart->copy()->subSecond(), // koniec przed pracą
                'source' => 'working_absence',
            ]);
        }

        if ($workEnd->lt($dayEnd)) {
            $intervals->push([
                'start' => $workEnd->copy()->addSecond(), // zaraz po pracy
                'end' => $dayEnd->copy(),
                'source' => 'working_absence',
            ]);
        }
    }

    // 3) Scalanie (merge) wszystkich przedziałów nakładających się
    // Sortujemy po starcie
    $sorted = $intervals->sortBy(function($i) { return $i['start']->timestamp; })->values();

    $merged = collect();
    foreach ($sorted as $it) {
        if ($merged->isEmpty()) {
            $merged->push($it);
            continue;
        }

        $last = $merged->last();

        // Jeśli aktualny start <= last.end + 1s => merge
        if ($it['start']->lessThanOrEqualTo($last['end']->copy()->addSecond())) {
            // poszerzamy last.end do max(last.end, it.end)
            $last['end'] = $it['end']->greaterThan($last['end']) ? $it['end']->copy() : $last['end']->copy();
            // zachowujemy source jako 'merged'
            $last['source'] = 'merged';
            // zamieniamy ostatni element
            $merged->pop();
            $merged->push($last);
        } else {
            $merged->push($it);
        }
    }

    // 4) Mapujemy wynik do formatu JSON (podobnego do urlopów)
    $result = $merged->map(function($it, $idx) use ($doctorId) {
        return [
            'id' => null,
            'doctor_id' => $doctorId,
            'doctor_name' => null,
            'doctor_surname' => null,
            'start_date' => $it['start']->toDateString(),
            'end_date' => $it['end']->toDateString(),
            'start_time' => $it['start']->toTimeString(), // HH:MM:SS
            'end_time' => $it['end']->toTimeString(),
            'source' => $it['source'] ?? null,
        ];
    })->values();

    return response()->json($result);
}

    public function indexOld(Request $request)
    {
        $week = $request->query('week');

        $query = Vacation::with('doctor');

        if ($week) {
            $dates = explode('-', $week);
            if (count($dates) === 2) {
                $startDateStr = trim($dates[0]);
                $endDateStr = trim($dates[1]);

                $startDate = Carbon::createFromFormat('d.m.Y', $startDateStr);
                $endDate = Carbon::createFromFormat('d.m.Y', $endDateStr);

                if ($startDate && $endDate) {
                    // Pobieramy wakacje które mają jakikolwiek overlap z tym tygodniem
                    $query->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate])
                            ->orWhere(function ($q2) use ($startDate, $endDate) {
                                $q2->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                            });
                    });
                }
            }
        } else {
            // $today = Carbon::today();
            // $query->whereDate('end_date', '>=', $today);
        }

        $vacations = $query->orderBy('start_date')->get();

        $result = $vacations->map(function ($vacation) {
            return [
                'id' => $vacation->id,
                'doctor_id' => $vacation->doctor_id,
                'doctor_name' => $vacation->doctor->name,
                'doctor_surname' => $vacation->doctor->surname,
                'start_date' => $vacation->start_date,
                'end_date' => $vacation->end_date,
                'start_time' => $vacation->start_time,
                'end_time' => $vacation->end_time,
            ];
        });

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
        ]);

        $vacation = Vacation::create([
            'doctor_id' => $request->doctor_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        return response()->json([
            'message' => 'Urlop dodany',
            'vacation' => $vacation,
        ], 201);
    }

    public function destroy($id)
    {
        $vacation = Vacation::find($id);

        if (!$vacation) {
            return response()->json(['message' => 'Urlop nie znaleziony'], 404);
        }

        $vacation->delete();

        return response()->json(['message' => 'Urlop usunięty']);
    }
}
