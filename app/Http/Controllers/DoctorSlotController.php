<?php

namespace App\Http\Controllers;

use App\Models\DoctorSlot;
use App\Services\DoctorSlotService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\DoctorWorkingHour;

class DoctorSlotController extends Controller
{
    public function index(Request $request, int $doctorId)
    {
        $date = $request->query('date', Carbon::now()->toDateString());

        $slots = DoctorSlot::where('doctor_id', $doctorId)
            ->whereDate('start_time', $date)
            ->orderBy('start_time')
            ->get();

        return response()->json($slots);
    }

    public function generate(Request $request, DoctorSlotService $service, int $doctorId)
    {
        $from = Carbon::parse($request->query('from', Carbon::now()));
        $to = Carbon::parse($request->query('to', Carbon::now()->addDays(14)));

        $service->generateSlots($doctorId, $from, $to);

        return response()->json(['message' => 'Sloty wygenerowane']);
    }

    public function getSlotsRangeTest(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'type' => 'nullable|in:available,reserved,vacation,all',
        ]);

        $doctorId = $validated['doctor_id'] ?? null;

        // 🔹 Pobierz grafiki pracy (doctor_working_hours)
        $workingHours = \App\Models\DoctorWorkingHour::query()
            ->when($doctorId, fn($q) => $q->where('doctor_id', $doctorId))
            ->get()
            ->groupBy(fn($h) => $h->doctor_id . '|' . $h->day_of_week);

        $from = Carbon::parse($validated['from'])->toDateString();
        $to = Carbon::parse($validated['to'])->toDateString();
        $type = $validated['type'] ?? 'all';

        $cacheKey = "slots:{$doctorId}:{$from}:{$to}:{$type}";

        $slots = Cache::remember($cacheKey, 60, function () use ($doctorId, $from, $to, $type) {
            $query = \App\Models\DoctorSlot::query()
                ->select(['doctor_id', 'date', 'start_time', 'end_time', 'type', 'visit_id'])
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to);

            if ($doctorId) {
                $query->where('doctor_id', $doctorId);
            }

            if ($type !== 'all') {
                $query->where('type', $type);
            }

            return $query
                ->orderBy('date')
                ->orderBy('start_time')
                ->get();
        });

        // 🔹 Wygeneruj sloty z grafików (DoctorWorkingHour)
        $generatedSlots = collect();
        $dates = \Carbon\CarbonPeriod::create($from, $to);

        foreach ($dates as $date) {
            $dayOfWeek = $date->dayOfWeekIso; // 1=pon, 7=niedz
            $doctors = $doctorId ? [$doctorId] : \App\Models\Doctor::pluck('id');

            foreach ($doctors as $id) {
                $schedule = $workingHours->get($id . '|' . $dayOfWeek)?->first();
                if (!$schedule) continue;

                $start = Carbon::parse($schedule->start_time);
                $end = Carbon::parse($schedule->end_time);
                $duration = 45; // minuty

                while ($start < $end) {
                    $slotEnd = (clone $start)->addMinutes($duration);
                    if ($slotEnd > $end) break;

                    $generatedSlots->push([
                        'doctor_id' => $id,
                        'date' => $date->format('Y-m-d'),
                        'start_time' => $start->format('H:i'),
                        'end_time' => $slotEnd->format('H:i'),
                        'type' => 'available',
                        'visit_id' => null,
                    ]);

                    $start->addMinutes($duration);
                }
            }
        }

        // 🔹 Połącz istniejące sloty (z bazy) i wygenerowane (z grafiku)
        $allSlots = $generatedSlots->merge($slots);

        // 🔹 Scalanie slotów dla jednej wizyty
        $groupedByVisit = $allSlots->groupBy(fn($slot) => $slot['visit_id'] ?: 'free');

        $mergedSlots = $groupedByVisit->flatMap(function ($group, $key) {
            if ($key !== 'free' && $group->count() > 0) {
                return [[
                    'doctor_id' => $group->first()['doctor_id'],
                    'date' => Carbon::parse($group->first()['date'])->format('Y-m-d'),
                    'start_time' => Carbon::parse($group->min('start_time'))->format('H:i'),
                    'end_time' => Carbon::parse($group->max('end_time'))->format('H:i'),
                    'type' => 'reserved',
                    'visit_id' => $key,
                ]];
            }

            return $group->map(function ($slot) {
                return [
                    'doctor_id' => $slot['doctor_id'],
                    'date' => $slot['date'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'type' => $slot['type'],
                    'visit_id' => $slot['visit_id'],
                ];
            });
        })->sortBy(['date', 'start_time'])->values();

        // 🔹 Grupowanie slotów po dacie i doktorze
        $groupedByDateDoctor = $mergedSlots->groupBy(function ($slot) {
            return $slot['date'] . '|' . $slot['doctor_id'];
        })->map(function ($slotsForDoctor) use ($workingHours) {
            $allDayFree = $slotsForDoctor->every(fn($slot) => $slot['type'] === 'available');

            return [
                'doctor_id' => $slotsForDoctor->first()['doctor_id'],
                // 'schedules' => $workingHours,
                'date' => $slotsForDoctor->first()['date'],
                'all_day_free' => $allDayFree,
                'slots' => $slotsForDoctor->values(),
            ];
        })->values();

        return response()->json($groupedByDateDoctor);
    }


    // public function getSlotsRangewwee(Request $request)
    // {
    //     $validated = $request->validate([
    //         'doctor_id' => 'nullable|integer|exists:doctors,id',
    //         'from' => 'required|date',
    //         'to' => 'required|date|after_or_equal:from',
    //         'type' => 'nullable|in:available,reserved,vacation,all',
    //     ]);

    //     $doctorId = $validated['doctor_id'] ?? null;
    //     $from = Carbon::parse($validated['from'])->toDateString();
    //     $to = Carbon::parse($validated['to'])->toDateString();
    //     $type = $validated['type'] ?? 'all';

    //     $slots = \App\Models\DoctorSlot::query()
    //         ->select(['doctor_id', 'date', 'start_time', 'end_time', 'type', 'visit_id'])
    //         ->whereDate('date', '>=', $from)
    //         ->whereDate('date', '<=', $to)
    //         ->when($doctorId, fn($q) => $q->where('doctor_id', $doctorId))
    //         ->when($type !== 'all', fn($q) => $q->where('type', $type))
    //         ->orderBy('date')
    //         ->orderBy('start_time')
    //         ->get()
    //         ->map(function ($slot) {
    //             return [
    //                 'doctor_id' => (int) $slot->doctor_id, // ujednolicamy na int
    //                 'date' => $slot->date,
    //                 'start_time' => $slot->start_time,
    //                 'end_time' => $slot->end_time,
    //                 'type' => $slot->type,
    //                 'visit_id' => $slot->visit_id ? (int) $slot->visit_id : null,
    //             ];
    //         })
    //         ->unique(fn($slot) => "{$slot['doctor_id']}_{$slot['date']}_{$slot['start_time']}_{$slot['end_time']}")
    //         ->values()
    //         ->groupBy(fn($slot) => $slot['doctor_id'] . '|' . $slot['date'])
    //         ->map(fn($slotsForDay) => [
    //             'doctor_id' => $slotsForDay->first()['doctor_id'],
    //             'date' => $slotsForDay->first()['date'],
    //             'all_day_free' => $slotsForDay->every(fn($s) => $s['type'] === 'available'),
    //             'slots' => $slotsForDay->sortBy('start_time')->values(),
    //         ])
    //         ->values();

    //     return response()->json([$slots]);
    // }


    public function getSlotsRange(Request $request)
{
    $validated = $request->validate([
        'doctor_id' => 'nullable|integer|exists:doctors,id',
        'from' => 'required|date',
        'to' => 'required|date|after_or_equal:from',
        'type' => 'nullable|in:available,reserved,vacation,all',
        'current_time' => 'nullable|date_format:H:i',
    ]);

    $doctorId = $validated['doctor_id'] ?? null;
    $from = Carbon::parse($validated['from'])->toDateString();
    $to = Carbon::parse($validated['to'])->toDateString();
    $type = $validated['type'] ?? 'all';

    $today = Carbon::now()->toDateString();
    $currentTime = $validated['current_time'] ?? Carbon::now()->toTimeString();

    // 1️⃣ Pobierz wszystkie sloty w zakresie
    $slots = DoctorSlot::query()
        ->select(['doctor_id', 'date', 'start_time', 'end_time', 'type', 'visit_id'])
        ->whereDate('date', '>=', $from)
        ->whereDate('date', '<=', $to)
        ->when($doctorId, fn($q) => $q->where('doctor_id', $doctorId))
        ->when($type !== 'all', fn($q) => $q->where('type', $type))
        ->orderBy('date')
        ->orderBy('start_time')
        ->get();

    // 2️⃣ Pobierz wszystkie godziny pracy dla tych doktorów w jednym zapytaniu
    $doctorIds = $slots->pluck('doctor_id')->unique()->toArray();
    $workingHours = DoctorWorkingHour::whereIn('doctor_id', $doctorIds)->get();

    // 3️⃣ Zmapuj godziny pracy do tablicy [doctor_id][day_of_week]
    $workingMap = [];
    foreach ($workingHours as $wh) {
        $workingMap[$wh->doctor_id][$wh->day_of_week] = $wh;
    }

    // 4️⃣ Filtruj sloty wg godzin pracy i current_time
    $slots = $slots->filter(function ($slot) use ($workingMap, $today, $currentTime) {
        $slotDate = Carbon::parse($slot->date);
        $dayOfWeek = $slotDate->dayOfWeek; // 0 = niedziela, 1 = poniedziałek, ...

        if (!isset($workingMap[$slot->doctor_id][$dayOfWeek])) {
            return false; // brak godzin pracy → nie pokazuj slotu
        }

        $wh = $workingMap[$slot->doctor_id][$dayOfWeek];

        // filtr godzin pracy
        if ($slot->start_time < $wh->start_time || $slot->end_time > $wh->end_time) {
            return false;
        }

        // jeśli slot jest dzisiaj → uwzględnij current_time
        if ($slot->date === $today && $slot->start_time <= $currentTime) {
            return false;
        }

        return true;
    });

    // 5️⃣ Mapowanie slotów na format JSON
    $slots = $slots->map(function ($slot) {
        return [
            'doctor_id' => (int) $slot->doctor_id,
            'date' => $slot->date,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'type' => $slot->type,
            'visit_id' => $slot->visit_id ? (int) $slot->visit_id : null,
        ];
    })
    ->unique(fn($slot) => "{$slot['doctor_id']}_{$slot['date']}_{$slot['start_time']}_{$slot['end_time']}")
    ->values()
    ->groupBy(fn($slot) => $slot['doctor_id'] . '|' . $slot['date'])
    ->map(fn($slotsForDay) => [
        'doctor_id' => $slotsForDay->first()['doctor_id'],
        'date' => $slotsForDay->first()['date'],
        'all_day_free' => $slotsForDay->every(fn($s) => $s['type'] === 'available'),
        'slots' => $slotsForDay->sortBy('start_time')->values(),
    ])
    ->values();


    return response()->json([$slots]);
}

    // public function getSlotsRangeOld(Request $request)
    // {
    //     $validated = $request->validate([
    //         'doctor_id' => 'nullable|integer|exists:doctors,id',
    //         'from' => 'required|date',
    //         'to' => 'required|date|after_or_equal:from',
    //         'type' => 'nullable|in:available,reserved,vacation,all',
    //     ]);

    //     $doctorId = $validated['doctor_id'] ?? null;
    //     $from = Carbon::parse($validated['from'])->toDateString();
    //     $to = Carbon::parse($validated['to'])->toDateString();
    //     $type = $validated['type'] ?? 'all';

    //     $cacheKey = "slots:{$doctorId}:{$from}:{$to}:{$type}";

    //     // $slots = Cache::remember($cacheKey, 60, function () use ($doctorId, $from, $to, $type) {
    //     //     $query = \App\Models\DoctorSlot::query()
    //     //         ->select(['doctor_id', 'date', 'start_time', 'end_time', 'type', 'visit_id'])
    //     //         ->whereDate('date', '>=', $from)
    //     //         ->whereDate('date', '<=', $to);

    //     //     if ($doctorId) {
    //     //         $query->where('doctor_id', $doctorId);
    //     //     }

    //     //     if ($type !== 'all') {
    //     //         $query->where('type', $type);
    //     //     }

    //     //     return $query
    //     //         ->orderBy('date')
    //     //         ->orderBy('start_time')
    //     //         ->get();
    //     // });
    //     // tymczasowo, tylko do testów:
    //     $slots = (function () use ($doctorId, $from, $to, $type) {
    //         $query = \App\Models\DoctorSlot::query()
    //             ->select(['doctor_id', 'date', 'start_time', 'end_time', 'type', 'visit_id'])
    //             ->whereDate('date', '>=', $from)
    //             ->whereDate('date', '<=', $to);

    //         if ($doctorId) {
    //             $query->where('doctor_id', $doctorId);
    //         }

    //         if ($type !== 'all') {
    //             $query->where('type', $type);
    //         }

    //         return $query
    //             ->orderBy('date')
    //             ->orderBy('start_time')
    //             ->get();
    //     })();

    //     // 🔹 Scalanie slotów dla jednej wizyty
    //     $groupedByVisit = $slots->groupBy(fn($slot) => $slot->visit_id ?: 'free');

    //     $mergedSlots = $groupedByVisit->flatMap(function ($group, $key) {
    //         if ($key !== 'free' && $group->count() > 0) {
    //             return [[
    //                 'doctor_id' => $group->first()->doctor_id,
    //                 'date' => Carbon::parse($group->first()->date)->format('Y-m-d'),
    //                 'start_time' => Carbon::parse($group->min('start_time'))->format('H:i'),
    //                 'end_time' => Carbon::parse($group->max('end_time'))->format('H:i'),
    //                 'type' => 'reserved',
    //                 'visit_id' => $key,
    //             ]];
    //         }

    //         return $group->map(function ($slot) {
    //             return [
    //                 'doctor_id' => $slot->doctor_id,
    //                 'date' => Carbon::parse($slot->date)->format('Y-m-d'),
    //                 'start_time' => Carbon::parse($slot->start_time)->format('H:i'),
    //                 'end_time' => Carbon::parse($slot->end_time)->format('H:i'),
    //                 'type' => $slot->type,
    //                 'visit_id' => $slot->visit_id,
    //             ];
    //         });
    //     })->sortBy(['date', 'start_time'])->values();

    //     // 🔹 Grupowanie slotów po dacie i doktorze
    //     $groupedByDateDoctor = $mergedSlots->groupBy(function ($slot) {
    //         return $slot['date'] . '|' . $slot['doctor_id'];
    //     })->map(function ($slotsForDoctor) {
    //         $allDayFree = $slotsForDoctor->every(fn($slot) => $slot['type'] === 'available');

    //         return [
    //             'doctor_id' => $slotsForDoctor->first()['doctor_id'],
    //             'date' => $slotsForDoctor->first()['date'],
    //             'all_day_free' => $allDayFree,
    //             'slots' => $slotsForDoctor->map(fn($slot) => [
    //                 'date' => $slot['date'],
    //                 'start_time' => $slot['start_time'],
    //                 'end_time' => $slot['end_time'],
    //                 'type' => $slot['type'],
    //                 'visit_id' => $slot['visit_id'],
    //             ])->values(),
    //         ];
    //     })->values();

    //     return response()->json($groupedByDateDoctor);
    // }

    public function getSlotsRange1(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'type' => 'nullable|in:available,reserved,vacation,all',
            'current_time' => 'nullable|date_format:H:i',
        ]);

        $doctorId = $validated['doctor_id'] ?? null;
        $from = Carbon::parse($validated['from'])->toDateString();
        $to = Carbon::parse($validated['to'])->toDateString();
        $type = $validated['type'] ?? 'all';

        $today = Carbon::now()->toDateString();
        $currentTime = $validated['current_time'] ?? Carbon::now()->format('H:i:s');

        // 1️⃣ Pobierz sloty w zakresie
        $slots = DoctorSlot::query()
            ->select(['doctor_id', 'date', 'start_time', 'end_time', 'type', 'visit_id'])
            ->whereBetween('date', [$from, $to])
            ->when($doctorId, fn($q) => $q->where('doctor_id', $doctorId))
            ->when($type !== 'all', fn($q) => $q->where('type', $type))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        if ($slots->isEmpty()) {
            return response()->json([]);
        }

        // 2️⃣ Pobierz godziny pracy
        $doctorIds = $slots->pluck('doctor_id')->unique()->toArray();
        $workingHours = DoctorWorkingHour::whereIn('doctor_id', $doctorIds)->get();

        // 3️⃣ Zmapuj godziny pracy wg dnia tygodnia
        $workingMap = [];
        foreach ($workingHours as $wh) {
            $workingMap[$wh->doctor_id][$wh->day_of_week] = $wh;
        }

        // 4️⃣ Filtruj sloty wg godzin pracy i current_time
        $slots = $slots->filter(function ($slot) use ($workingMap, $today, $currentTime) {
            $slotDate = Carbon::parse($slot->date);
            $dayOfWeek = $slotDate->dayOfWeekIso; // ✅ 1=pon, 7=niedz

            if (!isset($workingMap[$slot->doctor_id][$dayOfWeek])) {
                return false; // brak godzin pracy
            }

            $wh = $workingMap[$slot->doctor_id][$dayOfWeek];

            // ✅ Porównania czasów jako obiekty Carbon
            $slotStart = Carbon::parse($slot->start_time);
            $slotEnd = Carbon::parse($slot->end_time);
            $workStart = Carbon::parse($wh->start_time);
            $workEnd = Carbon::parse($wh->end_time);

            // ✅ Uwzględnij także pierwszą i ostatnią godzinę (<= i >=)
            if ($slotStart->lt($workStart) || $slotEnd->gt($workEnd)) {
                return false;
            }

            // ✅ Jeśli dzień to dzisiaj, odfiltruj przeszłe godziny
            if ($slot->date === $today && $slotStart->lte(Carbon::parse($currentTime))) {
                return false;
            }

            return true;
        });

        // 5️⃣ Mapowanie wyników
        $slots = $slots->map(function ($slot) {
            return [
                'doctor_id' => (int) $slot->doctor_id,
                'date' => $slot->date,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'type' => $slot->type,
                'visit_id' => $slot->visit_id ? (int) $slot->visit_id : null,
            ];
        })
            ->unique(fn($slot) => "{$slot['doctor_id']}_{$slot['date']}_{$slot['start_time']}_{$slot['end_time']}")
            ->values()
            ->groupBy(fn($slot) => $slot['doctor_id'] . '|' . $slot['date'])
            ->map(fn($slotsForDay) => [
                'doctor_id' => $slotsForDay->first()['doctor_id'],
                'date' => $slotsForDay->first()['date'],
                'all_day_free' => $slotsForDay->every(fn($s) => $s['type'] === 'available'),
                'slots' => $slotsForDay->sortBy('start_time')->values(),
            ])
            ->values();

        return response()->json($slots);
    }

    // Endpoint do „rollowania” slotów – usuwa dzisiejsze i generuje na kolejny dzień
    public function rollTodaySlots(DoctorSlotService $slotService)
    {
        // Data dzisiejsza
        $today = Carbon::today()->toDateString();
        // $today = '2025-10-20'; // na sztywno, żeby przetestować
        // Pobieramy wszystkich lekarzy, którzy mają sloty dzisiaj
        $doctorIds = \App\Models\DoctorSlot::where('date', $today)
            ->distinct()
            ->pluck('doctor_id');

        $results = [];

        foreach ($doctorIds as $doctorId) {
            // 1️⃣ Usuwamy sloty dzisiaj dla tego lekarza
            $deleted = \App\Models\DoctorSlot::where('doctor_id', $doctorId)
                ->where('date', $today)
                ->delete();

            Log::info("Deleted $deleted slots for doctor $doctorId for today ($today)");

            // 2️⃣ Pobieramy ostatni dzień w slotach po usunięciu dzisiejszych
            $lastSlotDate = \App\Models\DoctorSlot::where('doctor_id', $doctorId)
                ->orderByDesc('date')
                ->value('date');

            $lastDate = $lastSlotDate ? Carbon::parse($lastSlotDate) : Carbon::today();

            // 3️⃣ Generujemy sloty dla kolejnego dnia po ostatnim
            $nextDay = $lastDate->copy()->addDay();

            $slotService->generateSlots(
                doctorId: $doctorId,
                from: $nextDay,
                to: $nextDay,
                slotLengthMinutes: 45
            );

            Log::info("Generated slots for doctor $doctorId on {$nextDay->toDateString()}");

            $results[] = [
                'doctor_id' => $doctorId,
                'today_deleted' => $today,
                'next_day_generated' => $nextDay->toDateString(),
            ];
        }

        return response()->json([
            'message' => 'Rolled today\'s slots successfully',
            'results' => $results,
        ]);
    }
}
