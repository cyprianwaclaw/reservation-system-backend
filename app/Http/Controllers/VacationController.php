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

    public function indexTest(Request $request)
{
        // $week = $request->query('week');

        // $query = Vacation::with('doctor');

        // if ($week) {
        //     $dates = explode('-', $week);
        //     if (count($dates) === 2) {
        //         $startDateStr = trim($dates[0]);
        //         $endDateStr = trim($dates[1]);

        //         $startDate = Carbon::createFromFormat('d.m.Y', $startDateStr);
        //         $endDate   = Carbon::createFromFormat('d.m.Y', $endDateStr);

        //         if ($startDate && $endDate) {
        //             $query->where(function ($q) use ($startDate, $endDate) {
        //                 $q->whereBetween('start_date', [$startDate, $endDate])
        //                     ->orWhereBetween('end_date', [$startDate, $endDate])
        //                     ->orWhere(function ($q2) use ($startDate, $endDate) {
        //                         $q2->where('start_date', '<=', $startDate)
        //                             ->where('end_date', '>=', $endDate);
        //                     });
        //             });
        //         }
        //     }
        // }

        // $vacations = $query->orderBy('start_date')->get();

        // // ---------------------------------------
        // // DODAJEMY GODZINY POZA PRACĄ
        // // ---------------------------------------

        // // Pobieramy godziny pracy wszystkich lekarzy
        // $workingHours = DoctorWorkingHour::with('doctor')->get();

        // // Sloty ramowe
        // $dayStart = Carbon::createFromTime(7, 30);
        // $dayEnd   = Carbon::createFromTime(21, 0);

        // $extra = [];

        // foreach ($workingHours as $wh) {

        //     $doc = $wh->doctor;

        //     // godziny pracy lekarza
        //     $workStart = Carbon::parse($wh->start_time);
        //     $workEnd   = Carbon::parse($wh->end_time);

        //     // 1. Jeśli pracuje od 8:15 → wcześniejszy brak pracy 07:30–08:15
        //     if ($workStart->greaterThan($dayStart)) {
        //         $extra[] = [
        //             'id'            => null,
        //             'doctor_id'     => $doc->id,
        //             'doctor_name'   => $doc->name,
        //             'doctor_surname'=> $doc->surname,
        //             'start_date'    => null,
        //             'end_date'      => null,
        //             'start_time'    => $dayStart->format('H:i'),
        //             'end_time'      => $workStart->format('H:i'),
        //             'type'          => 'generated', // oznaczenie
        //             'day_of_week'   => $wh->day_of_week
        //         ];
        //     }

        //     // 2. Jeśli kończy o 16 → brak pracy 16:00–21:00
        //     if ($workEnd->lessThan($dayEnd)) {
        //         $extra[] = [
        //             'id'            => null,
        //             'doctor_id'     => $doc->id,
        //             'doctor_name'   => $doc->name,
        //             'doctor_surname'=> $doc->surname,
        //             'start_date'    => null,
        //             'end_date'      => null,
        //             'start_time'    => $workEnd->format('H:i'),
        //             'end_time'      => $dayEnd->format('H:i'),
        //             'type'          => 'generated',
        //             'day_of_week'   => $wh->day_of_week
        //         ];
        //     }
        // }

        // // mapowanie wakacji
        // $result = $vacations->map(function ($vacation) {
        //     return [
        //         'id'            => $vacation->id,
        //         'doctor_id'     => $vacation->doctor_id,
        //         'doctor_name'   => $vacation->doctor->name,
        //         'doctor_surname'=> $vacation->doctor->surname,
        //         'start_date'    => $vacation->start_date,
        //         'end_date'      => $vacation->end_date,
        //         'start_time'    => $vacation->start_time,
        //         'end_time'      => $vacation->end_time,
        //         'type'          => 'vacation'
        //     ];
        // })->toArray();

        // // scal prawdziwe wakacje + generowane
        // $full = array_merge($result, $extra);

        // return response()->json($full);


        //    $week = $request->query('week');

        // $query = Vacation::with('doctor');

        // if ($week) {
        //     $dates = explode('-', $week);
        //     if (count($dates) === 2) {
        //         $startDateStr = trim($dates[0]);
        //         $endDateStr   = trim($dates[1]);

        //         $startDate = Carbon::createFromFormat('d.m.Y', $startDateStr)->startOfDay();
        //         $endDate   = Carbon::createFromFormat('d.m.Y', $endDateStr)->endOfDay();

        //         if ($startDate && $endDate) {
        //             $query->where(function ($q) use ($startDate, $endDate) {
        //                 $q->whereBetween('start_date', [$startDate, $endDate])
        //                     ->orWhereBetween('end_date', [$startDate, $endDate])
        //                     ->orWhere(function ($q2) use ($startDate, $endDate) {
        //                         $q2->where('start_date', '<=', $startDate)
        //                            ->where('end_date', '>=', $endDate);
        //                     });
        //             });
        //         }
        //     }
        // }

        // $vacations = $query->orderBy('start_date')->get();

        // // ---------------------------------------
        // // GODZINY PRACY → SLOTY POZA PRACĄ
        // // ---------------------------------------

        // $workingHours = DoctorWorkingHour::with('doctor')->get();

        // $dayStart = Carbon::createFromTime(7, 00);
        // $dayEnd   = Carbon::createFromTime(21, 0);

        // $extra = [];

        // foreach ($workingHours as $wh) {
        //     $doc = $wh->doctor;

        //     $workStart = Carbon::parse($wh->start_time);
        //     $workEnd   = Carbon::parse($wh->end_time);

        //     // Konwersja day_of_week na datę w podanym tygodniu
        //     // 1 = Poniedziałek, 7 = Niedziela
        //     $dayDate = Carbon::parse($startDateStr)->startOfWeek()->addDays($wh->day_of_week - 1);

        //     // Slot przed pracą
        //     if ($workStart->greaterThan($dayStart)) {
        //         $extra[] = [
        //             'id'            => null,
        //             'doctor_id'     => $doc->id,
        //             'doctor_name'   => $doc->name,
        //             'doctor_surname'=> $doc->surname,
        //             'start_date'    => $dayDate->format('Y-m-d'),
        //             'end_date'      => $dayDate->format('Y-m-d'),
        //             'start_time'    => $dayStart->format('H:i'),
        //             'end_time'      => $workStart->format('H:i'),
        //             'type'          => 'generated',
        //             'day_of_week'   => $wh->day_of_week
        //         ];
        //     }

        //     // Slot po pracy
        //     if ($workEnd->lessThan($dayEnd)) {
        //         $extra[] = [
        //             'id'            => null,
        //             'doctor_id'     => $doc->id,
        //             'doctor_name'   => $doc->name,
        //             'doctor_surname'=> $doc->surname,
        //             'start_date'    => $dayDate->format('Y-m-d'),
        //             'end_date'      => $dayDate->format('Y-m-d'),
        //             'start_time'    => $workEnd->format('H:i'),
        //             'end_time'      => $dayEnd->format('H:i'),
        //             'type'          => 'generated',
        //             'day_of_week'   => $wh->day_of_week
        //         ];
        //     }
        // }

        // // mapowanie wakacji
        // $result = $vacations->map(function ($vacation) {
        //     return [
        //         'id'            => $vacation->id,
        //         'doctor_id'     => $vacation->doctor_id,
        //         'doctor_name'   => $vacation->doctor->name,
        //         'doctor_surname'=> $vacation->doctor->surname,
        //         'start_date'    => $vacation->start_date,
        //         'end_date'      => $vacation->end_date,
        //         'start_time'    => $vacation->start_time,
        //         'end_time'      => $vacation->end_time,
        //         'type'          => 'vacation'
        //     ];
        // })->toArray();

        // $full = array_merge($result, $extra);

        // return response()->json($full);


        // return response()->json($full);

        $week = $request->query('week');

        $query = Vacation::with('doctor');

        // -----------------------------------
        // Parsowanie tygodnia z query
        // -----------------------------------
        if ($week) {
            $dates = explode('-', $week);
            if (count($dates) === 2) {
                $startDateStr = trim($dates[0]);
                $endDateStr   = trim($dates[1]);

                $startDate = Carbon::createFromFormat('d.m.Y', $startDateStr)->startOfDay();
                $endDate   = Carbon::createFromFormat('d.m.Y', $endDateStr)->endOfDay();

                if ($startDate && $endDate) {
                    // Pobieramy wakacje z overlapem z tym zakresem
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
            $startDate = Carbon::today();
            $endDate   = Carbon::today();
        }

        $vacations = $query->orderBy('start_date')->get();

        // -----------------------------------
        // Generowanie slotów poza godzinami pracy
        // -----------------------------------
        $workingHours = DoctorWorkingHour::with('doctor')->get();

        $dayStart = Carbon::createFromTime(7, 30);
        $dayEnd   = Carbon::createFromTime(21, 0);

        $extra = [];

        $currentDate = $startDate->copy();
        while ($currentDate->lessThanOrEqualTo($endDate)) {

            $dayOfWeek = $currentDate->dayOfWeekIso; // 1 = Poniedziałek, 7 = Niedziela

            // dla każdego lekarza
            $doctors = $workingHours->groupBy('doctor_id');
            foreach ($doctors as $docId => $hours) {

                $doc = $hours->first()->doctor;

                // Sprawdź, czy lekarz ma godziny pracy w tym dniu
                $workHour = $hours->firstWhere('day_of_week', $dayOfWeek);

                if (!$workHour) {
                    // brak godzin pracy → cały dzień wolny
                    $extra[] = [
                        'id'             => null,
                        'doctor_id'      => $doc->id,
                        'doctor_name'    => $doc->name,
                        'doctor_surname' => $doc->surname,
                        'start_date'     => $currentDate->format('Y-m-d'),
                        'end_date'       => $currentDate->format('Y-m-d'),
                        'start_time'     => '00:00',
                        'end_time'       => '00:00',
                        'type'           => 'generated',
                        'day_of_week'    => $dayOfWeek,
                    ];
                    continue;
                }

                $workStart = Carbon::parse($workHour->start_time);
                $workEnd   = Carbon::parse($workHour->end_time);

                // slot przed pracą
                if ($workStart->greaterThan($dayStart)) {
                    $extra[] = [
                        'id'             => null,
                        'doctor_id'      => $doc->id,
                        'doctor_name'    => $doc->name,
                        'doctor_surname' => $doc->surname,
                        'start_date'     => $currentDate->format('Y-m-d'),
                        'end_date'       => $currentDate->format('Y-m-d'),
                        'start_time'     => $dayStart->format('H:i'),
                        'end_time'       => $workStart->format('H:i'),
                        'type'           => 'generated',
                        'day_of_week'    => $dayOfWeek,
                    ];
                }

                // slot po pracy
                if ($workEnd->lessThan($dayEnd)) {
                    $extra[] = [
                        'id'             => null,
                        'doctor_id'      => $doc->id,
                        'doctor_name'    => $doc->name,
                        'doctor_surname' => $doc->surname,
                        'start_date'     => $currentDate->format('Y-m-d'),
                        'end_date'       => $currentDate->format('Y-m-d'),
                        'start_time'     => $workEnd->format('H:i'),
                        'end_time'       => $dayEnd->format('H:i'),
                        'type'           => 'generated',
                        'day_of_week'    => $dayOfWeek,
                    ];
                }
            }

            $currentDate->addDay();
        }

        // -----------------------------------
        // Mapowanie wakacji z bazy
        // -----------------------------------
        $result = $vacations->map(function ($vacation) {
            return [
                'id'             => $vacation->id,
                'doctor_id'      => $vacation->doctor_id,
                'doctor_name'    => $vacation->doctor->name,
                'doctor_surname' => $vacation->doctor->surname,
                'start_date'     => $vacation->start_date,
                'end_date'       => $vacation->end_date,
                'start_time'     => $vacation->start_time,
                'end_time'       => $vacation->end_time,
                'type'           => 'vacation',
            ];
        })->toArray();

        // scalamy prawdziwe wakacje + generowane
        $full = array_merge($result, $extra);

        return response()->json($full);

}

    public function index(Request $request)
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
