<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vacation;
use Carbon\Carbon;

use Illuminate\Validation\ValidationException;

class VacationController extends Controller
{
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

