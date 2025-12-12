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

    if (!$week) {
        return response()->json(['message' => 'week is required (e.g. 10.12.2025 - 16.12.2025)'], 400);
    }

    // Parsowanie zakresu
    $dates = explode('-', $week);
    if (count($dates) !== 2) {
        return response()->json(['message' => 'invalid week format'], 400);
    }

    $startDate = Carbon::createFromFormat('d.m.Y', trim($dates[0]))->startOfDay();
    $endDate = Carbon::createFromFormat('d.m.Y', trim($dates[1]))->endOfDay();

    // Pobieramy:
    // 1) wszystkich lekarzy
    // 2) urlopy wszystkich lekarzy w zakresie
    // 3) wszystkie grafiki pracy
    $doctors = \App\Models\Doctor::all();
    $vacations = Vacation::where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
              ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_date', '<=', $startDate->toDateString())
                     ->where('end_date', '>=', $endDate->toDateString());
              });
        })
        ->get()
        ->groupBy('doctor_id');

    $workingHours = \App\Models\DoctorWorkingHour::all()
        ->groupBy('doctor_id')
        ->map(function ($items) {
            return $items->keyBy('day_of_week');
        });

    $intervals = collect();

    // Iterujemy po każdym lekarzu
    foreach ($doctors as $doctor) {
        $doctorId = $doctor->id;

        // 1) Dodajemy urlopy lekarza
        foreach ($vacations->get($doctorId, []) as $vac) {
            $vacStart = Carbon::parse($vac->start_date . ' ' . ($vac->start_time ?? '00:00:00'));
            $vacEnd = Carbon::parse($vac->end_date . ' ' . ($vac->end_time ?? '23:59:59'));

            $start = $vacStart->greaterThan($startDate) ? $vacStart : $startDate;
            $end = $vacEnd->lessThan($endDate) ? $vacEnd : $endDate;

            if ($start->lte($end)) {
                $intervals->push([
                    'doctor_id' => $doctorId,
                    'start' => $start->copy(),
                    'end' => $end->copy(),
                    'source' => 'vacation'
                ]);
            }
        }

        // 2) Dodajemy bloki "poza godzinami pracy"
        $doctorWorking = $workingHours->get($doctorId, collect());

        $period = CarbonPeriod::create($startDate->toDateString(), $endDate->toDateString());
        /** @var \Carbon\Carbon $day */
        foreach ($period as $day) {
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();
            $dow = $day->dayOfWeekIso;

            $work = $doctorWorking->get($dow);

            // Brak pracy -> cały dzień niedostępny
            if (!$work) {
                $intervals->push([
                    'doctor_id' => $doctorId,
                    'start' => $dayStart->copy(),
                    'end' => $dayEnd->copy(),
                    'source' => 'working_absence'
                ]);
                continue;
            }

            $workStart = Carbon::parse($day->toDateString() . ' ' . $work->start_time);
            $workEnd = Carbon::parse($day->toDateString() . ' ' . $work->end_time);

            // Jeśli 00–00 → zero godzin pracy
            if ($workStart->eq($workEnd)) {
                $intervals->push([
                    'doctor_id' => $doctorId,
                    'start' => $dayStart->copy(),
                    'end' => $dayEnd->copy(),
                    'source' => 'working_absence'
                ]);
                continue;
            }

            // Blok przed pracą
            if ($dayStart->lt($workStart)) {
                $intervals->push([
                    'doctor_id' => $doctorId,
                    'start' => $dayStart->copy(),
                    'end' => $workStart->copy()->subSecond(),
                    'source' => 'working_absence'
                ]);
            }

            // Blok po pracy
            if ($workEnd->lt($dayEnd)) {
                $intervals->push([
                    'doctor_id' => $doctorId,
                    'start' => $workEnd->copy()->addSecond(),
                    'end' => $dayEnd->copy(),
                    'source' => 'working_absence'
                ]);
            }
        }
    }

    // 3) Scalanie oddzielnie dla każdego lekarza
    $merged = collect();

    foreach ($intervals->groupBy('doctor_id') as $doctorId => $items) {
        $sorted = $items->sortBy(fn ($i) => $i['start']->timestamp)->values();
        $buffer = collect();

        foreach ($sorted as $it) {
            if ($buffer->isEmpty()) {
                $buffer->push($it);
                continue;
            }

            $last = $buffer->last();

            if ($it['start']->lessThanOrEqualTo($last['end']->copy()->addSecond())) {
                $last['end'] = $it['end']->greaterThan($last['end'])
                    ? $it['end']->copy()
                    : $last['end']->copy();

                $last['source'] = 'merged';
                $buffer->pop();
                $buffer->push($last);
            } else {
                $buffer->push($it);
            }
        }

        $merged = $merged->merge($buffer);
    }

    // 4) Formatowanie JSON
    $result = $merged->map(function ($it) {
        return [
            'doctor_id' => $it['doctor_id'],
            'start_date' => $it['start']->toDateString(),
            'end_date' => $it['end']->toDateString(),
            'start_time' => $it['start']->toTimeString(),
            'end_time' => $it['end']->toTimeString(),
            'source' => $it['source']
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