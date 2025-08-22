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
            // $isReserved = Visit::where('doctor_id', $doctorId)
            //     ->whereDate('date', $date)
            //     ->where(function ($q) use ($current, $slotEnd) {
            //         $q->whereBetween('start_time', [$current->format('H:i'), $slotEnd->format('H:i')])
            //           ->orWhereBetween('end_time', [$current->format('H:i'), $slotEnd->format('H:i')]);
            //     })
            //     ->exists();

            $isReserved = Visit::where('doctor_id', $doctorId)
                ->whereDate('date', $date->toDateString())
                ->where('start_time', '<', $slotEnd->format('H:i'))
                ->where('end_time',   '>', $current->format('H:i'))
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
                $freeSlots = $this->generateDailySlots($doctor->id, $date);

                if ($date->isToday()) {
                    $now = Carbon::now('Europe/Warsaw'); // teraz w lokalnej strefie
                    $freeSlots = array_filter($freeSlots, function ($slot) use ($now, $date) {
                        // łączenie daty slotu z lokalną strefą
                        $slotTime = Carbon::createFromFormat('Y-m-d H:i', $date->toDateString() . ' ' . $slot, 'Europe/Warsaw');
                        return $slotTime->greaterThan($now); // tylko sloty w przyszłości
                    });
                }

                $now = Carbon::now()->setTimezone('Europe/Warsaw');
                if (!empty($freeSlots)) {
                    $firstFreeSlot = reset($freeSlots); // pierwszy wolny slot z przefiltrowanych

                    $availableDoctorsForDate[] = [
                        'doctor_id' => $doctor->id,
                        'name' => $doctor->name,
                        'surname' => $doctor->surname,
                        // 'phone' => $doctor->phone,
                        // 'email' => $doctor->email,
                        'free_slots' => array_values($freeSlots),
                        // 'first_free_slot' => $firstFreeSlot,
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


    public function getFullyAvailableDaysForDoctor(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'start_date' => 'nullable|date',
            'days_ahead' => 'nullable|integer|min:1|max:60',
        ]);

        $doctorId = $request->input('doctor_id');
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::today();
        $daysAhead = $request->input('days_ahead') ?? 20; // default na 20 dni

        $result = [];
        $doctor = Doctor::findOrFail($doctorId);

        for ($i = 0; $i < $daysAhead; $i++) {
            $date = $startDate->copy()->addDays($i);

            // Pomijamy weekendy
            if ($date->isWeekend()) {
                continue;
            }

            // Pobieramy wolne sloty
            $freeSlots = $this->generateDailySlots($doctor->id, $date);

            // Generujemy wszystkie możliwe sloty dnia (bez uwzględnienia wizyt/przerw)
            $workStart = Carbon::parse($date->toDateString() . ' 08:00');
            $workEnd = Carbon::parse($date->toDateString() . ' 18:45');
            $allSlots = [];
            $current = $workStart->copy();
            while ($current->lt($workEnd)) {
                $slotEnd = $current->copy()->addMinutes(45);
                if ($slotEnd->gt($workEnd)) break;
                $allSlots[] = $current->format('H:i');
                $current->addMinutes(45);
            }

            // Jeśli wszystkie sloty są wolne, dodajemy dzień do wyniku
            if (!empty($freeSlots) && count($freeSlots) === count($allSlots)) {
                $result[] = [
                    'value' => $date->toDateString(),
                    'label' => $date->format('d.m.Y'),
                    // Carbon::parse($visit->date)->format('d.m.Y')
                ];
            }
        }

        return response()->json($result);
    }

    public function getEndOptions(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'date' => 'required|date',
            'start' => 'required|date_format:H:i',
        ]);

        $doctorId = $request->doctor_id;
        $date = Carbon::parse($request->date);
        $start = $request->start;

        // Pobierz wszystkie wolne sloty dla lekarza w tym dniu
        $allSlots = $this->generateDailySlots($doctorId, $date);

        // Znajdź indeks startowego slotu
        $startIndex = array_search($start, $allSlots);
        if ($startIndex === false) {
            return response()->json([
                'error' => 'Podany start slotu jest niedostępny'
            ], 400);
        }

        // Wytnij wszystkie kolejne sloty po startowym
        $endOptionsRaw = array_slice($allSlots, $startIndex + 1);

        // Przekształć na format { value, label } 1:1
        $endOptions = array_map(function ($slot) {
            return [
                'value' => $slot,
                'label' => $slot
            ];
        }, $endOptionsRaw);

        return response()->json($endOptions);
    }


    public function addVacations(Request $request)
    {
        $validated = $request->validate([
            'doctor_id'  => 'required|exists:doctors,id',
            'date'       => 'required|date',
            'all_day'    => 'required|boolean',
            'start_time' => 'nullable|required_if:all_day,false|date_format:G:i',
            'end_time'   => 'nullable|required_if:all_day,false|date_format:G:i|after:start_time',
        ]);

        // Jeśli all_day = true → przypisujemy godziny 07:00 - 21:00
        $startTime = $validated['all_day'] ? '8:00' : $validated['start_time'];
        $endTime   = $validated['all_day'] ? ' 18:45' : $validated['end_time'];

        $vacation = Vacation::create([
            'doctor_id'  => $validated['doctor_id'],
            'start_date' => $validated['date'],
            'end_date'   => $validated['date'],
            'start_time' => $startTime,
            'end_time'   => $endTime,
        ]);

        return response()->json([
            'message'  => 'Wakacje dodane pomyślnie',
            'vacation' => $vacation
        ], 201);
    }


    public function getAvailableDays1(Request $request)
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

                // Jeśli dzisiaj, filtrujemy sloty w przeszłości
                if ($date->isToday()) {
                    $currentTime = Carbon::now()->format('H:i');
                    $freeSlots = array_filter($freeSlots, fn ($slot) => $slot > $currentTime);
                }
                // if ($date->isToday()) {
                //     $now = Carbon::now();
                //     $freeSlots = array_filter($freeSlots, function ($slot) use ($now, $date) {
                //             $slotTime = Carbon::parse($date->toDateString() . ' ' . $slot);
                //             return $slotTime->greaterThan($now); // lub ->gte($now) jeśli chcesz włączyć bieżącą minutę
                //         });
                // }
                if (!empty($freeSlots)) {
                    // Pierwszy wolny slot
                    $firstFreeSlot = reset($freeSlots);

                    $availableDoctorsForDate[] = [
                        'doctor_id' => $doctor->id,
                        'name' => $doctor->name,
                        'surname' => $doctor->surname,
                        'phone' => $doctor->phone,
                        'email' => $doctor->email,
                        'free_slots' => array_values($freeSlots), // reset indeksów
                        'first_free_slot' => $firstFreeSlot,
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

    // public function getAvailableDays(Request $request)
    // {
    //     $request->validate([
    //         'start_date' => 'nullable|date',
    //         'days_ahead' => 'nullable|integer|min:1|max:60',
    //     ]);

    //     $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::today();
    //     $daysAhead = $request->days_ahead ?? 30;

    //     $result = [];

    //     for ($i = 0; $i < $daysAhead; $i++) {
    //         $date = $startDate->copy()->addDays($i);

    //         // Pomijamy weekendy
    //         if ($date->isWeekend()) {
    //             continue;
    //         }

    //         $availableDoctorsForDate = [];
    //         $doctors = Doctor::all();

    //         foreach ($doctors as $doctor) {
    //             // Generujemy sloty 45-minutowe z uwzględnieniem wakacji/przerw i zajętych wizyt
    //             $freeSlots = $this->generateDailySlots($doctor->id, $date);

    //             if (!empty($freeSlots)) {
    //                 $availableDoctorsForDate[] = [
    //                     'doctor_id' => $doctor->id,
    //                     'name' => $doctor->name,
    //                     'surname' => $doctor->surname,
    //                     'phone' => $doctor->phone,
    //                     'email' => $doctor->email,
    //                     'free_slots' => $freeSlots,
    //                 ];
    //             }
    //         }

    //         if (!empty($availableDoctorsForDate)) {
    //             $result[] = [
    //                 'date' => $date->toDateString(),
    //                 'doctors' => $availableDoctorsForDate,
    //             ];
    //         }
    //     }

    //     return response()->json($result);
    // }


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
            'doctor_id'   => 'required|exists:doctors,id',
            'name'        => 'required|string|max:255',
            'surname'     => 'required|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'email'       => 'required|email|max:255',
            'date'        => 'required|date',
            'start_time'  => 'required|date_format:H:i',
            'duration'    => 'required|integer|min:1|max:480', // np. max 8h
        ]);

        $reservationStart = Carbon::parse($request->date . ' ' . $request->start_time);
        $reservationEnd   = $reservationStart->copy()->addMinutes($request->duration);

        // Sprawdź czy termin jest w przeszłości (zarówno start jak i end)
        if ($reservationEnd->lt(Carbon::now())) {
            return response()->json(['message' => 'Nie można rezerwować zakończonych terminów'], 422);
        }

        // Sprawdź weekend
        if ($reservationStart->isWeekend()) {
            return response()->json(['message' => 'Nie można rezerwować w weekendy'], 422);
        }

        // Sprawdź urlop lekarza (uwzględniając godziny)
        $hasVacation = Vacation::where('doctor_id', $request->doctor_id)
            ->where(function ($q) use ($reservationStart, $reservationEnd) {
                $q->whereRaw('TIMESTAMP(start_date, COALESCE(start_time, "00:00:00")) < ?', [$reservationEnd])
                    ->whereRaw('TIMESTAMP(end_date,   COALESCE(end_time, "23:59:59")) > ?', [$reservationStart]);
            })
            ->exists();

        if ($hasVacation) {
            return response()->json(['message' => 'Lekarz jest na urlopie w tym terminie'], 422);
        }

        // Sprawdź czy user istnieje lub utwórz nowego
        $user = User::firstOrCreate(
            ['email' => $request->email],
            [
                'name'     => $request->name,
                'surname'  => $request->surname,
                'phone'    => $request->phone,
                'password' => "password", // losowe hasło, zahashowane
            ]
        );

        // Sprawdź kolizję slotów (start lub end zachodzi na inne wizyty)
        $exists = Visit::where('doctor_id', $request->doctor_id)
            ->where('date', $reservationStart->toDateString())
            ->where(function ($query) use ($reservationStart, $reservationEnd) {
                $query->where('start_time', '<', $reservationEnd->format('H:i'))
                ->where('end_time',   '>', $reservationStart->format('H:i'));
            })
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Slot już zajęty'], 409);
        }

        // Tworzymy wizytę
        Visit::create([
            'doctor_id'  => $request->doctor_id,
            'user_id'    => $user->id,
            'date'       => $reservationStart->toDateString(),
            'start_time' => $reservationStart->format('H:i'),
            'end_time'   => $reservationEnd->format('H:i'),
        ]);

        return response()->json(['message' => 'Zarezerwowano'], 201);
    }

    // public function reserve(Request $request)
    // {
    //     $request->validate([
    //         'doctor_id'   => 'required|exists:doctors,id',
    //         'name'        => 'required|string|max:255',
    //         'surname'     => 'required|string|max:255',
    //         'phone'       => 'nullable|string|max:20',
    //         'email'       => 'required|email|max:255',
    //         'date'        => 'required|date',
    //         'start_time'  => 'required|date_format:H:i',
    //         'duration'    => 'required|integer|min:1|max:480', // np. max 8h
    //     ]);

    //     $reservationStart = Carbon::parse($request->date . ' ' . $request->start_time);
    //     $reservationEnd   = $reservationStart->copy()->addMinutes($request->duration);

    //     // Sprawdź czy termin jest w przeszłości
    //     if ($reservationStart->lt(Carbon::now())) {
    //         return response()->json(['message' => 'Nie można rezerwować terminów w przeszłości'], 422);
    //     }

    //     // Sprawdź weekend
    //     if ($reservationStart->isWeekend()) {
    //         return response()->json(['message' => 'Nie można rezerwować w weekendy'], 422);
    //     }

    //     // Sprawdź urlop lekarza
    //     $hasVacation = Vacation::where('doctor_id', $request->doctor_id)
    //         ->whereDate('start_date', '<=', $reservationStart->toDateString())
    //         ->whereDate('end_date', '>=', $reservationStart->toDateString())
    //         ->exists();

    //     if ($hasVacation) {
    //         return response()->json(['message' => 'Lekarz jest na urlopie w tym dniu'], 422);
    //     }

    //     // Sprawdź czy user istnieje lub utwórz nowego
    //     $user = User::firstOrCreate(
    //         ['email' => $request->email],
    //         [
    //             'name'     => $request->name,
    //             'surname'  => $request->surname,
    //             'phone'    => $request->phone,
    //             'password' => bcrypt(str()->random(12)),
    //         ]
    //     );

    //     // Sprawdź kolizję slotów (start lub end zachodzi na inne wizyty)
    //     $exists = Visit::where('doctor_id', $request->doctor_id)
    //         ->where('date', $request->date)
    //         ->where(function ($query) use ($reservationStart, $reservationEnd) {
    //             $query->whereBetween('start_time', [$reservationStart->format('H:i'), $reservationEnd->format('H:i')])
    //                 ->orWhereBetween('end_time', [$reservationStart->format('H:i'), $reservationEnd->format('H:i')])
    //                 ->orWhere(function ($q) use ($reservationStart, $reservationEnd) {
    //                     $q->where('start_time', '<', $reservationStart->format('H:i'))
    //                     ->where('end_time', '>', $reservationEnd->format('H:i'));
    //                 });
    //         })
    //         ->exists();

    //     if ($exists) {
    //         return response()->json(['message' => 'Slot już zajęty'], 409);
    //     }

    //     Visit::create([
    //         'doctor_id'  => $request->doctor_id,
    //         'user_id'    => $user->id,
    //         'date'       => $reservationStart->toDateString(),
    //         'start_time' => $reservationStart->format('H:i'),
    //         'end_time'   => $reservationEnd->format('H:i'),
    //     ]);

    //     return response()->json(['message' => 'Zarezerwowano']);
    // }

    public function reserveOld(Request $request)
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



    public function allUsers(Request $request)
    {
        $limit = $request->get('limit', 100);
        $offset = $request->get('offset', 0);
        $search = $request->get('search', '');

        $query = User::select('id', 'name', 'surname')
            ->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%");
            });
        }

        // Pobranie aktualnej partii
        $users = $query->offset($offset)->limit($limit)->get();

        // Sprawdzenie, czy są dalsze rekordy
        $nextOffset = $offset + $limit;
        $hasMore = $query->count() > $nextOffset;

        $grouped = $users->groupBy(function ($user) {
            return strtoupper(substr($user->name, 0, 1));
        });

        return response()->json([
            'data' => $grouped,
            'hasMore' => $hasMore,
            'nextOffset' => $nextOffset
        ]);
    }

    // Zwrócenie danych pojedynczego usera
    public function userByID($id)
    {
        $user = User::findOrFail($id);
        // // Pobranie wizyt
        // $visits = $user->visits()
        //     ->get()
        //     ->map(function ($visit) {
        //         return [
        //             // 'id'         => $visit->id,
        //             // 'doctor_id'  => $visit->doctor_id,
        //             // 'user_id'    => $visit->user_id,
        //             'date'       => $visit->date,
        //             'start_time' => $visit->start_time,
        //             'end_time'   => $visit->end_time,
        //             'created_at' => $visit->created_at,
        //             'updated_at' => $visit->updated_at,
        //             'doctor'     => $visit->doctor ? [
        //                 'id'      => $visit->doctor->id,
        //                 'name'    => $visit->doctor->name,
        //                 // 'surname' => $visit->doctor->surname,
        //                 // 'email'   => $visit->doctor->email
        //             ] : null
        //         ];
        //     });

        // Pobranie notatek (ręcznie mapujemy visit do notatki)
        $notes = $user->notes()
            ->get()
            // format('H:i')
            ->map(function ($note) {
                $visit = $note->visit;
                return [
                    // 'id'         => $note->id,
                    // 'visit_id'   => $note->visit_id,
                    // 'note_date'  =>  Carbon::parse($note->note_date)->format('d.m.Y'),
                    'text'       => $note->text,
                    'attachments' => $note->attachments,
                    // 'created_at' => $note->created_at,
                    // 'updated_at' => $note->updated_at,
                    'visit_details'      => $visit ? [
                        // 'id'         => $visit->id,
                        // 'doctor_id'  => $visit->doctor_id,
                        // 'user_id'    => $visit->user_id,
                        'date' => Carbon::parse($visit->date)->format('d.m.Y'),
                        'start_time' => Carbon::parse($visit->start_time)->format('H:i'),
                        'end_time'   => Carbon::parse($visit->end_time)->format('H:i'),
                        // 'created_at' => $visit->created_at,
                        // 'updated_at' => $visit->updated_at,
                        'doctor'     => $visit->doctor ? [
                            // 'id'      => $visit->doctor->id,
                            'name'    => $visit->doctor->name,
                            'surname' => $visit->doctor->surname,
                            // 'email'   => $visit->doctor->email
                        ] : null
                    ] : null
                ];
            });

        // Zwracamy wszystko jako ręcznie mapowany JSON
        return response()->json([
            'id'      => $user->id,
            'name'    => $user->name,
            'surname' => $user->surname,
            'email'   => $user->email,
            // 'visits'  => $visits,
            'visits'   => $notes
        ]);
    }

    public function updateUserData(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Walidacja danych
        $validated = $request->validate([
            'name'    => 'sometimes|string|max:255',
            'surname' => 'sometimes|string|max:255',
            'email'   => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ]);

        // Aktualizacja danych
        $user->update($validated);

        // Zwracamy zaktualizowanego usera
        return response()->json([
            'message' => 'User updated successfully',
            'user'    => [
                'id'      => $user->id,
                'name'    => $user->name,
                'surname' => $user->surname,
                'email'   => $user->email,
            ]
        ]);
    }


    public function addPatient(Request $request)
    {
        // Walidacja
        $request->validate([
            'name'        => 'required|string|max:255',
            'surname'     => 'required|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'email'       => 'required|email|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        // Sprawdzenie, czy user istnieje
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            return response()->json([
                'message' => 'Pacjent o tym e-mailu już istnieje',
                'user_id' => $existingUser->id
            ], 409); // Conflict
        }

        // Tworzenie nowego usera jako pacjenta
        $user = User::create([
            'name'        => $request->name,
            'surname'     => $request->surname,
            'phone'       => $request->phone,
            'email'       => $request->email,
            'password'    => "password", // generujemy losowe hasło
        ]);

        // Możemy zapisać description w dodatkowej tabeli np. notes, jeśli chcesz
        // albo dodać kolumnę 'description' w users i ją tutaj uzupełnić
        if ($request->description) {
            $user->description = $request->description;
            $user->save();
        }

        return response()->json([
            'message' => 'Pacjent dodany pomyślnie',
            'user' => $user
        ], 201);
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
