<?php

namespace App\Http\Controllers;

use App\Models\DoctorSlot;
use App\Services\DoctorSlotService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

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

    public function reserve(Request $request, DoctorSlotService $service)
    {
        $service->reserveSlots(
            doctorId: $request->doctor_id,
            date: $request->date,
            visitStart: $request->start_time,
            visitEnd: $request->end_time,
            visitId: $request->visit_id
        );

        return response()->json(['message' => 'Sloty zarezerwowane']);
    }

    public function free(Request $request, DoctorSlotService $service)
    {
        $service->freeSlots($request->visit_id);

        return response()->json(['message' => 'Sloty zwolnione']);
    }

    public function reserveMulti(Request $request, DoctorSlotService $service)
    {
        $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i:s',
            'visit_minutes' => 'required|integer|min:15',
            'visit_id' => 'required|integer|exists:visits,id',
        ]);

        try {
            $service->reserveMultiSlot(
                $request->doctor_id,
                $request->date,
                $request->start_time,
                $request->visit_minutes,
                $request->visit_id
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Sloty zarezerwowane']);
    }



    // public function getSlotsRange(Request $request)
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
    //     $type = $validated['type'] ?? 'available';

    //     $cacheKey = "slots:{$doctorId}:{$from}:{$to}:{$type}";

    //     $slots = Cache::remember($cacheKey, 60, function () use ($doctorId, $from, $to, $type) {
    //         $query = \App\Models\DoctorSlot::query()
    //             ->select(['doctor_id', 'date', 'start_time', 'end_time', 'type', 'visit_id'])
    //             ->whereBetween('date', [$from, $to]); // <--- Używamy date zamiast start_time

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
    //     });

    //     $slots = $slots->map(function ($slot) {
    //         return [
    //             'doctor_id' => $slot->doctor_id,
    //             'date' => Carbon::parse($slot->date)->format('Y.m.d'), // poprawione
    //             'start_time' => Carbon::parse($slot->start_time)->format('H:i'),
    //             'end_time' => Carbon::parse($slot->end_time)->format('H:i'),
    //             'type' => $slot->type,
    //             'visit_id' => $slot->visit_id,
    //         ];
    //     });

    //     return response()->json($slots);
    // }


    // public function getSlotsRange(Request $request)
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
    //     $type = $validated['type'] ?? 'available';

    //     $cacheKey = "slots:{$doctorId}:{$from}:{$to}:{$type}";

    //     $slots = Cache::remember($cacheKey, 60, function () use ($doctorId, $from, $to, $type) {
    //         $query = \App\Models\DoctorSlot::query()
    //             ->select(['doctor_id', 'date', 'start_time', 'end_time', 'type', 'visit_id'])
    //             ->whereBetween('date', [$from, $to]);

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
    //     });

    //     // 🔹 SCALANIE slotów przypisanych do jednej wizyty (1h, 1.5h itd.)
    //     $grouped = $slots->groupBy(fn($slot) => $slot->visit_id ?: 'free');

    //     $merged = $grouped->flatMap(function ($group, $key) {
    //         if ($key !== 'free' && $group->count() > 0) {
    //             // rezerwacja (złączone sloty)
    //             return [[
    //                 'doctor_id' => $group->first()->doctor_id,
    //                 'date' => Carbon::parse($group->first()->date)->format('Y-m-d'),
    //                 'start_time' => Carbon::parse($group->min('start_time'))->format('H:i'),
    //                 'end_time' => Carbon::parse($group->max('end_time'))->format('H:i'),
    //                 'type' => 'reserved',
    //                 'visit_id' => $key,
    //             ]];
    //         }

    //         // wolne / vacation / inne pojedyncze sloty
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

    //     return response()->json($merged);
    // }
    public function getSlotsRange(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'type' => 'nullable|in:available,reserved,vacation,all',
        ]);

        $doctorId = $validated['doctor_id'] ?? null;
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

        // 🔹 Scalanie slotów dla jednej wizyty
        $grouped = $slots->groupBy(fn($slot) => $slot->visit_id ?: 'free');

        $merged = $grouped->flatMap(function ($group, $key) {
            if ($key !== 'free' && $group->count() > 0) {
                return [[
                    'doctor_id' => $group->first()->doctor_id,
                    'date' => $group->first()->date,
                    'start_time' => Carbon::parse($group->min('start_time'))->format('H:i'),
                    'end_time' => Carbon::parse($group->max('end_time'))->format('H:i'),
                    'type' => 'reserved',
                    'visit_id' => $key,
                ]];
            }

            return $group->map(function ($slot) {
                return [
                    'doctor_id' => $slot->doctor_id,
                    'date' => $slot->date,
                    'start_time' => Carbon::parse($slot->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($slot->end_time)->format('H:i'),
                    'type' => $slot->type,
                    'visit_id' => $slot->visit_id,
                ];
            });
        })->sortBy(['date', 'start_time'])->values();

        return response()->json($merged);
    }

}