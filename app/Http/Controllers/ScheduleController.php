<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Visit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{

private function generateDailySlots(int $doctorId, Carbon $date)
{
    // Godziny pracy
    $workStart = Carbon::parse($date->toDateString() . ' 08:00');
    $workEnd = Carbon::parse($date->toDateString() . ' 18:45');

    // Pobierz przerwy/wakacje
    $vacations = Vacation::where('doctor_id', $doctorId)
        ->whereDate('start_date', '<=', $date)
        ->whereDate('end_date', '>=', $date)
        ->get();

    $blockedPeriods = [];
    foreach ($vacations as $vacation) {
        if ($vacation->start_time && $vacation->end_time) {
            $blockedPeriods[] = [
                'start' => Carbon::parse($date->toDateString() . ' ' . $vacation->start_time),
                'end' => Carbon::parse($date->toDateString() . ' ' . $vacation->end_time),
            ];
        }
    }

    $current = $workStart->copy();
    $slots = [];

    while ($current->lt($workEnd)) {
        $slotEnd = $current->copy()->addMinutes(45);

        // Slot nie może wychodzić poza godziny pracy
        if ($slotEnd->gt($workEnd)) {
            break;
        }

        // Sprawdź kolizję z przerwami/wakacjami
        $isBlocked = false;
        foreach ($blockedPeriods as $period) {
            if ($slotEnd->gt($period['start']) && $current->lt($period['end'])) {
                $isBlocked = true;
                break;
            }
        }

        // Sprawdź kolizję z istniejącymi wizytami
        $isReserved = Visit::where('doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->where(function ($q) use ($current, $slotEnd) {
                $q->whereBetween('start_time', [$current->format('H:i'), $slotEnd->format('H:i')])
                  ->orWhereBetween('end_time', [$current->format('H:i'), $slotEnd->format('H:i')]);
            })
            ->exists();

        if (!$isBlocked && !$isReserved) {
            $slots[] = $current->format('H:i');
        }

        // Przejdź do następnego slotu
        $current->addMinutes(45);
    }

    return $slots;
}

    private function generateDailySlotsOLD($doctorId, Carbon $date)
    {
        $schedule = Schedule::where('doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->first();

        if ($schedule) {
            $start = Carbon::parse($schedule->start_time);
            $end = Carbon::parse($schedule->end_time)->subHour();
        } else {
            $start = $date->copy()->setTime(8, 0);
            $end = $date->copy()->setTime(19, 0); // subHour, bo slot trwa 1h
        }

        $hours = [];
        $period = CarbonPeriod::create($start, '1 hour', $end);

        foreach ($period as $hour) {
            $hourStr = $hour->format('H:i');

            $isReserved = Visit::where('doctor_id', $doctorId)
                ->whereDate('date', $date)
                ->whereTime('start_time', $hourStr)
                ->exists();

            if (!$isReserved) {
                $hours[] = $hourStr;
            }
        }

        return $hours;
    }



    private function isOnVacationAtTime(int $doctorId, Carbon $date, string $time): bool
    {
        $vacations = Vacation::where('doctor_id', $doctorId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get();

        foreach ($vacations as $vacation) {
            if (is_null($vacation->start_time) || is_null($vacation->end_time)) {
                return true;
            }

            if ($time >= $vacation->start_time && $time < $vacation->end_time) {
                return true;
            }
        }

        return false;
    }

    public function addNote(Request $request, $visitId)
    {
        $request->validate([
            'text' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // max 10MB
        ]);

        $visit = Visit::find($visitId);
        if (!$visit) {
            return response()->json(['error' => 'Nie znaleziono wizyty'], 404);
        }

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('visit_notes', 'public');
                $attachments[] = [
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                ];
            }
        }

        $note = $visit->notes()->create([
            'note_date' => now(),
            'text' => $request->text,
            'attachments' => json_encode($attachments),
        ]);

        return response()->json([
            'success' => true,
            'note' => $note,
        ]);
    }

    public function getVisitById($id)
    {
        $visit = Visit::with(['doctor', 'user', 'notes'])->find($id);

        if (!$visit) {
            return response()->json(['error' => 'Nie znaleziono wizyty'], 404);
        }

        // Pobranie wszystkich poprzednich wizyt pacjenta (po dacie)
        $previousVisits = Visit::with('doctor')
            ->where('user_id', $visit->user_id)
            ->where('date', '<', $visit->date)
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($prevVisit) {
                $date = Carbon::parse($prevVisit->date)->format('d.m.Y');
                $start = Carbon::parse($prevVisit->start_time)->format('H:i');
                $end = Carbon::parse($prevVisit->end_time)->format('H:i');

                return [
                    'full_date' => "{$date}, {$start} - {$end}",
                ];
            });
        // 'visit_id' => $prevVisit->id,
        // 'date' => Carbon::parse($prevVisit->date)->format('d.m.Y');
        // 'start_time' => $prevVisit-> start_time->format('H:i'),
        // 'end_time' => $prevVisit-> end_time->format('H:i'),
        // 'doctor' => [
        //     'name' => $prevVisit->doctor->name,
        //     'surname' => $prevVisit->doctor->surname,
        // ],

        return response()->json([
            'current_visit' => [
                'id' => $visit->id,
                'date' => Carbon::parse($visit->date)->format('d.m.Y'),
                'start_time' => Carbon::parse($visit->start_time)->format('H:i'),
                'end_time' => Carbon::parse($visit->end_time)->format('H:i'),
            ],
            // 'doctor' => [
            //     'name' => $visit->doctor->name,
            //     'surname' => $visit->doctor->surname,
            // ],
            'user' => [
                'id' => $visit->user->id,
                'name' => $visit->user->name,
                'surname' => $visit->user->surname,
                'e_mail' => $visit->user->email,
                'phone' => $visit->user->phone,
            ],
            'date' => $visit->date,
            'start_time' => $visit->start_time,
            'end_time' => $visit->end_time,
            'notes' => $visit->notes
                ->sortByDesc('note_date')
                ->values()
                ->map(function ($note) {
                    return [
                        'note_date' => $note->note_date->format('d.m.Y'),
                        'text' => $note->text,
                        'attachments' => $note->attachments,
                    ];
                }),
            // 'notes' => $visit->notes->map(function ($note) {
            //     return [
            //         'note_date' => $note->note_date->format('d.m.Y'),
            //         'text' => $note->text,
            //         'attachments' => $note->attachments,
            //     ];
            // }),
            'previous_visits' => $previousVisits,
        ]);
    }

    public function getAvailableDays(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'days_ahead' => 'nullable|integer|min:1|max:60',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::today();
        $daysAhead = $request->days_ahead ?? 30;

        $result = [];

        for ($i = 0; $i < $daysAhead; $i++) {
            $date = $startDate->copy()->addDays($i);

            // Pomijamy weekendy
            if ($date->isWeekend()) {
                continue;
            }

            $availableDoctorsForDate = [];
            $doctors = Doctor::all();

            foreach ($doctors as $doctor) {
                // Generujemy sloty 45-minutowe z uwzględnieniem wakacji/przerw i zajętych wizyt
                $freeSlots = $this->generateDailySlots($doctor->id, $date);

                if (!empty($freeSlots)) {
                    $availableDoctorsForDate[] = [
                        'doctor_id' => $doctor->id,
                        'name' => $doctor->name,
                        'surname' => $doctor->surname,
                        'phone' => $doctor->phone,
                        'email' => $doctor->email,
                        'free_slots' => $freeSlots,
                    ];
                }
            }

            if (!empty($availableDoctorsForDate)) {
                $result[] = [
                    'date' => $date->toDateString(),
                    'doctors' => $availableDoctorsForDate,
                ];
            }
        }

        return response()->json($result);
    }


    public function getAvailableDays1122(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'days_ahead' => 'nullable|integer|min:1|max:60',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::today();
        $daysAhead = $request->days_ahead ?? 30;

        $result = [];

        for ($i = 0; $i < $daysAhead; $i++) {
            $date = $startDate->copy()->addDays($i);

            if ($date->isWeekend()) {
                continue;
            }

            $availableDoctorsForDate = [];

            $doctors = Doctor::all();

            foreach ($doctors as $doctor) {

                // Pobierz grafik lub domyślne godziny
                $schedule = Schedule::where('doctor_id', $doctor->id)
                    ->whereDate('date', $date)
                    ->first();

                if ($schedule) {
                    $start = Carbon::parse($schedule->start_time);
                    $end = Carbon::parse($schedule->end_time)->subHour();
                } else {
                    $start = $date->copy()->setTime(8, 0);
                    $end = $date->copy()->setTime(19, 0);
                }

                if ($date->isToday()) {
                    $minTime = Carbon::now()->addHours(2);
                    if ($minTime->gt($end)) {
                        continue;
                    }
                    if ($minTime->gt($start)) {
                        $start = $minTime;
                    }
                }

                $period = CarbonPeriod::create($start, '1 hour', $end);

                $freeSlots = [];

                foreach ($period as $slot) {
                    $slotStr = $slot->format('H:i');

                    $isReserved = Visit::where('doctor_id', $doctor->id)
                        ->whereDate('date', $date)
                        ->whereTime('start_time', $slotStr)
                        ->exists();

                    if (!$isReserved && !$this->isOnVacationAtTime($doctor->id, $date, $slotStr)) {
                        $freeSlots[] = $slotStr;
                    }
                }

                if (count($freeSlots) > 0) {
                    $availableDoctorsForDate[] = [
                        'doctor_id' => $doctor->id,
                        'name' => $doctor->name,
                        'surname' => $doctor->surname,
                        'phone' => $doctor->phone,
                        'email' => $doctor->email,
                        'free_slots' => $freeSlots,
                    ];
                }
            }

            if (count($availableDoctorsForDate) > 0) {
                $result[] = [
                    'date' => $date->toDateString(),
                    'doctors' => $availableDoctorsForDate,
                ];
            }
        }

        return response()->json($result);
    }

    public function getFreeDoctors(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($request->date);

        // Tylko pon–pt
        if ($date->isWeekend()) {
            return response()->json([]);
        }

        // Lista wszystkich lekarzy
        $doctors = Doctor::all();

        $availableDoctors = $doctors->filter(function ($doctor) use ($date) {
            // Sprawdź urlop
            $isOnVacation = Vacation::where('doctor_id', $doctor->id)
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->exists();

            if ($isOnVacation) return false;

            // Sprawdź czy ma wolne godziny
            $hours = $this->generateDailySlots($doctor->id, $date);
            return count($hours) > 0;
        })->values();

        return response()->json($availableDoctors);
    }
    public function getFreeSlotsForDoctor(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($request->date);

        // Tylko pon–pt
        if ($date->isWeekend()) {
            return response()->json([]);
        }

        $slots = $this->generateDailySlots($request->doctor_id, $date);

        return response()->json($slots);
    }

    public function getAllVisits(Request $request)
    {
        $week = $request->query('week'); // np. "18.08.2025 - 22.08.2025"

        if (!$week) {
            return response()->json(['error' => 'Brak parametru week'], 400);
        }

        // Rozbij na dwie daty
        // $dates = explode('-', $week);
        $dates = preg_split('/\s*-\s*/', $week);
        if (count($dates) !== 2) {
            return response()->json(['error' => 'Niepoprawny format parametru week'], 400);
        }

        $startDateStr = trim($dates[0]);
        $endDateStr = trim($dates[1]);

        $startDate = \DateTime::createFromFormat('d.m.Y', $startDateStr);
        $endDate = \DateTime::createFromFormat('d.m.Y', $endDateStr);

        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'Niepoprawny format dat'], 400);
        }

        $startDateFormatted = $startDate->format('Y-m-d');
        $endDateFormatted = $endDate->format('Y-m-d');

        // Pobierz wizyty z zakresu dat
        $visits = Visit::with(['doctor', 'user'])
        ->whereBetween('date', [$startDateFormatted, $endDateFormatted])
            ->get();

        $result = $visits->map(function ($visit) {
            return [
                'visit_id' => $visit->id,
                'doctor_id' => $visit->doctor->id,
                'doctor_name' => $visit->doctor->name,
                'doctor_surname' => $visit->doctor->surname,
                'user_name' => $visit->user->name,
                'user_surname' => $visit->user->surname,
                'date' => $visit->date,
                'start_time' => $visit->start_time,
                'end_time' => $visit->end_time,
            ];
        });

        return response()->json($result);
    }

    public function reserve(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'date' => 'required|date',
            'hour' => 'required|date_format:H:i',
        ]);

        $reservationDateTime = Carbon::parse($request->date . ' ' . $request->hour);

        // Sprawdź czy termin jest w przeszłości
        if ($reservationDateTime->lt(Carbon::now())) {
            return response()->json(['message' => 'Nie można rezerwować terminów w przeszłości'], 422);
        }

        // Sprawdź czy to nie weekend
        if ($reservationDateTime->isWeekend()) {
            return response()->json(['message' => 'Nie można rezerwować w weekendy'], 422);
        }

        // Sprawdź czy lekarz ma urlop w tym dniu
        $hasVacation = Vacation::where('doctor_id', $request->doctor_id)
            ->whereDate('start_date', '<=', $reservationDateTime->toDateString())
            ->whereDate('end_date', '>=', $reservationDateTime->toDateString())
            ->exists();

        if ($hasVacation) {
            return response()->json(['message' => 'Lekarz jest na urlopie w tym dniu'], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $request->name,
                'surname' => $request->surname,
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => bcrypt(str()->random(12)),
            ]);
        }

        $exists = Visit::where('doctor_id', $request->doctor_id)
            ->whereDate('date', $request->date)
            ->whereTime('start_time', $request->hour)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Slot już zajęty'], 409);
        }

        Visit::create([
            'doctor_id' => $request->doctor_id,
            'user_id' => $user->id,
            'date' => $request->date,
            'start_time' => $request->hour,
            'end_time' => Carbon::parse($request->hour)->addHour(),
        ]);

        return response()->json(['message' => 'Zarezerwowano']);
    }


    public function updateVisit(Request $request, $visitId)
    {
        Log::info('Dane requestu przed walidacją:', $request->all());

        // Walidacja z przechwyceniem wyjątków
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'hour' => 'required|date_format:H:i',
                'doctor_id' => 'required|exists:doctors,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Błąd walidacji:', $e->errors());
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        }

        $visit = Visit::find($visitId);
        if (!$visit) {
            return response()->json(['error' => 'Nie znaleziono wizyty'], 404);
        }

        $newDoctorId = $validated['doctor_id'];
        $newDateTime = Carbon::parse($validated['date'] . ' ' . $validated['hour']);
        $newEndTime = $newDateTime->copy()->addHour();

        // Sprawdź czy nowy termin nie jest w przeszłości
        if ($newDateTime->lt(Carbon::now())) {
            return response()->json(['error' => 'Nie można ustawić wizyty w przeszłości'], 422);
        }

        // Sprawdź czy to nie weekend
        if ($newDateTime->isWeekend()) {
            return response()->json(['error' => 'Nie można ustawić wizyty w weekendy'], 422);
        }

        // Sprawdź czy lekarz nie ma urlopu w tym dniu i godzinach
        $hasVacation = Vacation::where('doctor_id', $newDoctorId)
            ->whereDate('start_date', '<=', $newDateTime->toDateString())
            ->whereDate('end_date', '>=', $newDateTime->toDateString())
            ->where(function ($q) use ($newDateTime, $newEndTime) {
                $q->whereTime('start_time', '<', $newEndTime->format('H:i'))
                ->whereTime('end_time', '>', $newDateTime->format('H:i'));
            })
            ->exists();

        if ($hasVacation) {
            return response()->json(['error' => 'Lekarz jest na urlopie w tym czasie'], 422);
        }

        // Sprawdź czy slot jest wolny (pomijając obecną wizytę)
        $exists = Visit::where('doctor_id', $newDoctorId)
            ->whereDate('date', $validated['date'])
            ->whereTime('start_time', '<', $newEndTime->format('H:i'))
            ->whereTime('end_time', '>', $newDateTime->format('H:i'))
            ->where('id', '!=', $visit->id)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Slot jest już zajęty'], 409);
        }

        // Aktualizacja wizyty
        $visit->doctor_id = $newDoctorId;
        $visit->date = $validated['date'];
        $visit->start_time = $validated['hour'];
        $visit->end_time = $newEndTime;
        $visit->save();

        Log::info('Wizyta zaktualizowana:', $visit->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Wizyta zaktualizowana',
            'request' => $validated,
            'visit' => $visit
        ]);
    }


    public function updateVisit11(Request $request, $visitId)
    {
        Log::info('Dane requestu do updateVisit1:', $request->all());

        $request->validate([
            'date' => 'required|date',
            'hour' => 'required|date_format:H:i',
            'doctor_id' => 'required|exists:doctors,id', // sprawdzamy czy lekarz istnieje
        ]);

        $visit = Visit::find($visitId);
        if (!$visit) {
            return response()->json(['error' => 'Nie znaleziono wizyty'], 404);
        }

        $newDoctorId = $request->doctor_id;
        $newDateTime = Carbon::parse($request->date . ' ' . $request->hour);

        // Sprawdź czy nowy termin nie jest w przeszłości
        if ($newDateTime->lt(Carbon::now())) {
            return response()->json(['error' => 'Nie można ustawić wizyty w przeszłości'], 422);
        }

        // Sprawdź czy to nie weekend
        if ($newDateTime->isWeekend()) {
            return response()->json(['error' => 'Nie można ustawić wizyty w weekendy'], 422);
        }

        // Sprawdź czy lekarz nie ma urlopu w tym dniu
        $hasVacation = Vacation::where('doctor_id', $newDoctorId)
            ->whereDate('start_date', '<=', $newDateTime->toDateString())
            ->whereDate('end_date', '>=', $newDateTime->toDateString())
            ->exists();

        if ($hasVacation) {
            return response()->json(['error' => 'Lekarz jest na urlopie w tym dniu'], 422);
        }

        // Sprawdź czy slot jest wolny (pomijając obecną wizytę)
        $exists = Visit::where('doctor_id', $newDoctorId)
            ->whereDate('date', $request->date)
            ->whereTime('start_time', $request->hour)
            ->where('id', '!=', $visit->id)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Slot jest już zajęty'], 409);
        }

        // Aktualizacja wizyty
        $visit->doctor_id = $newDoctorId;
        $visit->date = $request->date;
        $visit->start_time = $request->hour;
        $visit->end_time = Carbon::parse($request->hour)->addHour();
        $visit->save();
        Log::info('Dane requestu do updateVisit1:', $request->all());
        return response()->json([
            'success' => true,
            'message' => 'Wizyta zaktualizowana',
            'request' => $request->all(),
            'visit' => $visit
        ]);
    }

    // Anulowanie wizyty
    public function cancel($id)
    {
        $deleted = Visit::where('id', $id)->delete();

        if ($deleted) {
            return response()->json(['message' => 'Anulowano']);
        }

        return response()->json(['message' => 'Nie znaleziono rezerwacji'], 404);
    }
}
