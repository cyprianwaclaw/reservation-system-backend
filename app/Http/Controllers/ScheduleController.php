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
use App\Http\Requests\NewVisitRequest;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Requests\DoctorLoginRequest;
use Illuminate\Support\Facades\Hash;
use App\Mail\VisitConfirmationMail;
use App\Mail\VisitCancelledMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Mail\VisitRescheduledSimpleMail;
use Illuminate\Support\Facades\DB;
use App\Models\DoctorWorkingHour;

class ScheduleController extends Controller
{

    // private function generateDailySlots(int $doctorId, Carbon $date)
    // {

    //     $dayOfWeek = $date->dayOfWeekIso; // 1=poniedziałek, 7=niedziela
    //     $workingHour = DoctorWorkingHour::where('doctor_id', $doctorId)
    //         ->where('day_of_week', $dayOfWeek)
    //         ->first();

    //     if ($workingHour) {
    //         $workStart = Carbon::parse($date->toDateString() . ' ' . $workingHour->start_time);
    //         $workEnd = Carbon::parse($date->toDateString() . ' ' . $workingHour->end_time);
    //     } else {
    //         $workStart = Carbon::parse($date->toDateString() . ' 7:30');
    //         $workEnd = Carbon::parse($date->toDateString() . ' 21:00');
    //     }


    //         // Godziny pracy
    //         $workStart = Carbon::parse($date->toDateString() . ' 7:30');
    //     $workEnd = Carbon::parse($date->toDateString() . ' 21:00');

    //     // Pobierz przerwy/wakacje
    //     $vacations = Vacation::where('doctor_id', $doctorId)
    //         ->whereDate('start_date', '<=', $date)
    //         ->whereDate('end_date', '>=', $date)
    //         ->get();

    //     $blockedPeriods = [];
    //     foreach ($vacations as $vacation) {
    //         if ($vacation->start_time && $vacation->end_time) {
    //             $blockedPeriods[] = [
    //                 'start' => Carbon::parse($date->toDateString() . ' ' . $vacation->start_time),
    //                 'end' => Carbon::parse($date->toDateString() . ' ' . $vacation->end_time),
    //             ];
    //         }
    //     }

    //     $current = $workStart->copy();
    //     $slots = [];

    //     while ($current->lt($workEnd)) {
    //         $slotEnd = $current->copy()->addMinutes(45);

    //         // Slot nie może wychodzić poza godziny pracy
    //         if ($slotEnd->gt($workEnd)) {
    //             break;
    //         }

    //         // Sprawdź kolizję z przerwami/wakacjami
    //         $isBlocked = false;
    //         foreach ($blockedPeriods as $period) {
    //             if ($slotEnd->gt($period['start']) && $current->lt($period['end'])) {
    //                 $isBlocked = true;
    //                 break;
    //             }
    //         }

    //         // Sprawdź kolizję z istniejącymi wizytami
    //         // $isReserved = Visit::where('doctor_id', $doctorId)
    //         //     ->whereDate('date', $date)
    //         //     ->where(function ($q) use ($current, $slotEnd) {
    //         //         $q->whereBetween('start_time', [$current->format('H:i'), $slotEnd->format('H:i')])
    //         //           ->orWhereBetween('end_time', [$current->format('H:i'), $slotEnd->format('H:i')]);
    //         //     })
    //         //     ->exists();

    //         $isReserved = Visit::where('doctor_id', $doctorId)
    //             ->whereDate('date', $date->toDateString())
    //             ->where('start_time', '<', $slotEnd->format('H:i'))
    //             ->where('end_time',   '>', $current->format('H:i'))
    //             ->exists();

    //         if (!$isBlocked && !$isReserved) {
    //             $slots[] = $current->format('H:i');
    //         }

    //         // Przejdź do następnego slotu
    //         $current->addMinutes(45);
    //     }

    //     return $slots;
    // }}

    private function generateDailySlots(int $doctorId, Carbon $date, int $slotLengthMinutes = 45): array
    {
        $dayOfWeek = $date->dayOfWeekIso; // 1 = poniedziałek, 7 = niedziela

        // Pobierz grafik dla danego lekarza i dnia tygodnia
        $workingHour = DoctorWorkingHour::where('doctor_id', $doctorId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$workingHour) {
            // Jeśli lekarz nie pracuje tego dnia – zwróć pustą tablicę
            return [];
        }

        $workStart = Carbon::parse("{$date->toDateString()} {$workingHour->start_time}");
        $workEnd   = Carbon::parse("{$date->toDateString()} {$workingHour->end_time}");

        // Pobierz przerwy/wakacje dla tego dnia
        $vacations = Vacation::where('doctor_id', $doctorId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get();

        $blockedPeriods = collect($vacations)->map(function ($vacation) use ($date) {
            if ($vacation->start_time && $vacation->end_time) {
                return [
                    'start' => Carbon::parse("{$date->toDateString()} {$vacation->start_time}"),
                    'end'   => Carbon::parse("{$date->toDateString()} {$vacation->end_time}"),
                ];
            }
            return null;
        })->filter()->values();

        $slots = [];
        $current = $workStart->copy();

        while ($current->lt($workEnd)) {
            $slotEnd = $current->copy()->addMinutes($slotLengthMinutes);

            if ($slotEnd->gt($workEnd)) {
                break;
            }

            $isBlocked = $blockedPeriods->contains(function ($period) use ($current, $slotEnd) {
                return $slotEnd->gt($period['start']) && $current->lt($period['end']);
            });

            $isReserved = Visit::where('doctor_id', $doctorId)
                ->whereDate('date', $date->toDateString())
                ->where('start_time', '<', $slotEnd->format('H:i'))
                ->where('end_time', '>', $current->format('H:i'))
                ->exists();

            if (!$isBlocked && !$isReserved) {
                $slots[] = $current->format('H:i');
            }

            $current->addMinutes($slotLengthMinutes);
        }

        return $slots;
    }

    // private function generateDailySlotsOLD($doctorId, Carbon $date)
    // {
    //     $schedule = Schedule::where('doctor_id', $doctorId)
    //         ->whereDate('date', $date)
    //         ->first();

    //     if ($schedule) {
    //         $start = Carbon::parse($schedule->start_time);
    //         $end = Carbon::parse($schedule->end_time)->subHour();
    //     } else {
    //         $start = $date->copy()->setTime(8, 0);
    //         $end = $date->copy()->setTime(19, 0); // subHour, bo slot trwa 1h
    //     }

    //     $hours = [];
    //     $period = CarbonPeriod::create($start, '1 hour', $end);

    //     foreach ($period as $hour) {
    //         $hourStr = $hour->format('H:i');

    //         $isReserved = Visit::where('doctor_id', $doctorId)
    //             ->whereDate('date', $date)
    //             ->whereTime('start_time', $hourStr)
    //             ->exists();

    //         if (!$isReserved) {
    //             $hours[] = $hourStr;
    //         }
    //     }

    //     return $hours;
    // }



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
            'is_edit' => 'nullable|boolean', // nowy parametr
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
            'is_edit' => $request->has('is_edit') ? (bool)$request->is_edit : false,
        ]);

        return response()->json([
            'success' => true,
            'note' => $note,
        ]);
    }

    public function getVisitById($id)
    {
        // Jedno mega-query
        $data = DB::table('visits')
            ->leftJoin('users', 'visits.user_id', '=', 'users.id')
            ->leftJoin('doctors', 'visits.doctor_id', '=', 'doctors.id')
            ->leftJoin('visit_notes', 'visit_notes.visit_id', '=', 'visits.id')
            ->where('visits.id', $id)
            ->select(
                'visits.id as visit_id',
                'visits.type as visit_type',
                'visits.date as visit_date',
                'visits.start_time',
                'visits.end_time',
                'users.id as user_id',
                'users.name as user_name',
                'users.surname as user_surname',
                'users.email as user_email',
                'users.phone as user_phone',
                'users.age_with_suffix as user_age',
                'users.opis as user_description',
                'users.rodzaj_pacjenta as user_type',
                'doctors.name as doctor_name',
                'doctors.surname as doctor_surname',
                'visit_notes.id as note_id',
                'visit_notes.text as note_text',
                'visit_notes.attachments as note_attachments',
                'visit_notes.is_edit as note_is_edit',
                'visit_notes.created_at as note_created_at',
                'visit_notes.note_date as note_date'
            )
            ->orderBy('visit_notes.created_at')
            ->get();

        if ($data->isEmpty()) {
            return response()->json(['error' => 'Nie znaleziono wizyty'], 404);
        }

        // Pobieramy dane wizyty i użytkownika z pierwszego rekordu
        $first = $data->first();

        $current_visit = [
            'id' => $first->visit_id,
            'type' => $first->visit_type,
            'date' => Carbon::parse($first->visit_date)->format('d.m.Y'),
            'start_time' => Carbon::parse($first->start_time)->format('H:i'),
            'end_time' => Carbon::parse($first->end_time)->format('H:i'),
        ];

        $user = [
            'id' => $first->user_id,
            'name' => $first->user_name,
            'surname' => $first->user_surname,
            'email' => $first->user_email,
            'phone' => $first->user_phone,
            'age' => $first->user_age,
            'description' => $first->user_description,
            'type' => $first->user_type,
        ];

        // Mapujemy notatki
        $notes = $data->filter(fn($n) => !$n->note_is_edit)->map(function ($n) {
            return [
                'text' => $n->note_text,
                'attachments' => $n->note_attachments ? json_decode($n->note_attachments, true) : [],
                'visit_details' => $n->note_date ? [
                    'date' => Carbon::parse($n->note_date)->format('d.m.Y'),
                    'start_time' => Carbon::parse($n->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($n->end_time)->format('H:i'),
                    'doctor' => $n->doctor_name ? [
                        'name' => $n->doctor_name,
                        'surname' => $n->doctor_surname,
                    ] : null,
                ] : null
            ];
        });

        // Ostatnia szybka notatka
        $fast_note_model = $data->filter(fn($n) => $n->note_is_edit)->last();
        $fast_note = $fast_note_model ? [
            'note_date' => Carbon::parse($fast_note_model->note_created_at)->format('d.m.Y H:i'),
            'text' => $fast_note_model->note_text,
        ] : null;

        // Poprzednie wizyty – osobne małe query, tylko 5 ostatnich
        $previousVisits = DB::table('visits')
            ->where('user_id', $first->user_id)
            ->where('date', '<', $first->visit_date)
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get(['id', 'date', 'start_time', 'end_time'])
            ->map(fn($v) => [
                'full_date' => Carbon::parse($v->date)->format('d.m.Y') .
                    ', ' . Carbon::parse($v->start_time)->format('H:i') .
                    ' - ' . Carbon::parse($v->end_time)->format('H:i')
            ]);

        return response()->json([
            'current_visit' => $current_visit,
            'user' => $user,
            'date' => $first->visit_date,
            'start_time' => $first->start_time,
            'end_time' => $first->end_time,
            'notes' => $notes,
            'fast_note' => $fast_note,
            'previous_visits' => $previousVisits,
        ]);
    }


    public function getVisitByIdOld($id)
    {
        // 1️⃣ Pobranie wizyty + użytkownika + lekarza
        $visit = Visit::with(['doctor', 'user'])->find($id);

        if (!$visit) {
            return response()->json(['error' => 'Nie znaleziono wizyty'], 404);
        }

        $user = $visit->user;

        // 2️⃣ Poprzednie wizyty pacjenta (limit 5)
        $previousVisits = Visit::where('user_id', $user->id)
            ->where('date', '<', $visit->date)
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get(['id', 'date', 'start_time', 'end_time'])
            ->map(function ($v) {
                return [
                    'full_date' => Carbon::parse($v->date)->format('d.m.Y') .
                        ', ' . Carbon::parse($v->start_time)->format('H:i') .
                        ' - ' . Carbon::parse($v->end_time)->format('H:i'),
                ];
            });

        // 3️⃣ Notatki zwykłe i szybkie w jednym query z joinem
        $notesQuery = DB::table('visit_notes')
            ->leftJoin('visits', 'visit_notes.visit_id', '=', 'visits.id')
            ->leftJoin('doctors', 'visits.doctor_id', '=', 'doctors.id')
            ->where('visits.user_id', $user->id)
            ->select(
                'visit_notes.id',
                'visit_notes.text',
                'visit_notes.attachments',
                'visit_notes.is_edit',
                'visit_notes.created_at',
                'visits.date as visit_date',
                'visits.start_time',
                'visits.end_time',
                'doctors.name as doctor_name',
                'doctors.surname as doctor_surname'
            )
            ->orderBy('visit_notes.created_at')
            ->get();

        // Podział na zwykłe i szybkie notatki
        $notes = $notesQuery->where('is_edit', false)->map(function ($note) {
            return [
                'text' => $note->text,
                'attachments' => json_decode($note->attachments, true) ?? [],
                'visit_details' => $note->visit_date ? [
                    'date' => Carbon::parse($note->visit_date)->format('d.m.Y'),
                    'start_time' => Carbon::parse($note->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($note->end_time)->format('H:i'),
                    'doctor' => $note->doctor_name ? [
                        'name' => $note->doctor_name,
                        'surname' => $note->doctor_surname,
                    ] : null
                ] : null
            ];
        });

        $fast_note_model = $notesQuery->where('is_edit', true)->last();
        $fast_note = $fast_note_model ? [
            'note_date' => Carbon::parse($fast_note_model->created_at)->format('d.m.Y H:i'),
            'text' => $fast_note_model->text,
        ] : null;

        // 4️⃣ Zwracamy dane
        return response()->json([
            'current_visit' => [
                'id' => $visit->id,
                'type' => $visit->type,
                'date' => Carbon::parse($visit->date)->format('d.m.Y'),
                'start_time' => Carbon::parse($visit->start_time)->format('H:i'),
                'end_time' => Carbon::parse($visit->end_time)->format('H:i'),
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'surname' => $user->surname,
                'email' => $user->email,
                'phone' => $user->phone,
                'age' => $user->age_with_suffix,
                'description' => $user->opis,
                'type' => $user->rodzaj_pacjenta,
            ],
            'date' => $visit->date,
            'start_time' => $visit->start_time,
            'end_time' => $visit->end_time,
            'notes' => $notes,
            'fast_note' => $fast_note,
            'previous_visits' => $previousVisits,
        ]);
    }


    public function getVisitById1($id)
    {
        $visit = Visit::with(['doctor', 'user', 'notes'])->find($id);

        if (!$visit) {
            return response()->json(['error' => 'Nie znaleziono wizyty'], 404);
        }
        $user = $visit->user;
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


        $notes = $user->notes()
            ->get()
            ->where('is_edit', false)
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

        // Notatki zwykłe (is_edit = false)
        // $notes = $visit->notes
        //     ->where('is_edit', false)
        //     ->sortByDesc('created_at') // sortujemy malejąco po timestampie
        //     ->values()
        //     ->map(function ($note) {
        //         return [
        //             'note_date' => $note->created_at->format('d.m.Y H:i'), // dokładny timestamp
        //             'text' => $note->text,
        //             'attachments' => $note->attachments,
        //         ];
        //     });

        // Ostatnia notatka szybka (is_edit = true)
        $fast_note_model = $visit->notes
            ->where('is_edit', true)
            ->sortBy('created_at') // rosnąco po czasie
            ->last(); // bierze ostatnią (najpóźniejszą)

        $fast_note = $fast_note_model
            ? [
                'note_date' => $fast_note_model->created_at->format('d.m.Y H:i'),
                'text' => $fast_note_model->text,
                // 'attachments' => $fast_note_model->attachments,
            ]
            : null;

        return response()->json([
            'current_visit' => [
                'id' => $visit->id,
                'type' => $visit->type,
                'date' => Carbon::parse($visit->date)->format('d.m.Y'),
                'start_time' => Carbon::parse($visit->start_time)->format('H:i'),
                'end_time' => Carbon::parse($visit->end_time)->format('H:i'),
            ],
            'user' => [
                'id' => $visit->user->id,
                'name' => $visit->user->name,
                'surname' => $visit->user->surname,
                'email' => $visit->user->email,
                'phone' => $visit->user->phone,
                'age' => $visit->user->age_with_suffix,
                'description' => $visit->user->opis,
                'type' => $visit->user->rodzaj_pacjenta,
            ],
            'date' => $visit->date,
            'start_time' => $visit->start_time,
            'end_time' => $visit->end_time,
            'notes' => $notes,
            'fast_note' => $fast_note,
            // 'previous_visits' => $previousVisits,
        ]);
    }


    public function getAvailableDays(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'days_ahead' => 'nullable|integer|min:1|max:60',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::today();
        $daysAhead = $request->days_ahead ?? 50;

        // 🔐 Sprawdzenie czy jest token Bearer
        $authHeader = $request->header('Authorization');
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            // Jeśli brak tokenu → ogranicz do 15 dni
            $daysAhead = min($daysAhead, 15);
        }

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
                    $now = Carbon::now('Europe/Warsaw');
                    $freeSlots = array_filter($freeSlots, function ($slot) use ($now, $date) {
                        $slotTime = Carbon::createFromFormat('Y-m-d H:i', $date->toDateString() . ' ' . $slot, 'Europe/Warsaw');
                        return $slotTime->greaterThan($now);
                    });
                }

                if (!empty($freeSlots)) {
                    $availableDoctorsForDate[] = [
                        'doctor_id' => $doctor->id,
                        'name' => $doctor->name,
                        'surname' => $doctor->surname,
                        'free_slots' => array_values($freeSlots),
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
            'start_time' => 'nullable|required_if:all_day,false|date_format:H:i',
            'end_time'   => 'nullable|required_if:all_day,false|date_format:H:i|after:start_time',
        ]);

        // Jeśli all_day = true → przypisujemy godziny domyślne
        $startTime = $validated['all_day'] ? '7:00' : $validated['start_time'];
        $endTime   = $validated['all_day'] ? '21:00' : $validated['end_time'];

        $vacation = Vacation::create([
            'doctor_id'  => $validated['doctor_id'],
            'start_date' => $validated['date'],
            'end_date'   => $validated['date'],
            'start_time' => $startTime,
            'end_time'   => $endTime,
        ]);

        return response()->json([
            'message'  => 'Wakacje dodane',
            'vacation' => $vacation
        ], 201);
    }


    public function getAllVisits(Request $request)
    {
        $week = $request->query('week'); // np. "18.08.2025 - 22.08.2025"

        if (!$week) {
            return response()->json(['error' => 'Brak parametru week'], 400);
        }

        // Rozbijamy zakres tygodnia na dwie daty
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
        $visits = Visit::with(['doctor', 'user', 'notes'])->whereBetween('date', [
            $startDateFormatted,
            $endDateFormatted,
        ])->get();

        $result = $visits->map(function ($visit) {
            $user = $visit->user;

            // Ostatnia notatka użytkownika (is_edit = false)
            $lastUserNoteModel = $user
                ? $user->notes()
                ->where('is_edit', false)
                ->orderByDesc('created_at')
                ->first()
                : null;

            $lastUserNote = $lastUserNoteModel
                ? (function ($note) {
                    $visit = $note->visit;

                    return [
                        'text'        => $note->text,
                        'attachments' => $note->attachments,
                        'visit_details' => $visit ? [
                            'date'       => Carbon::parse($visit->date)->format('d.m.Y'),
                            'start_time' => Carbon::parse($visit->start_time)->format('H:i'),
                            'end_time'   => Carbon::parse($visit->end_time)->format('H:i'),
                            'doctor'     => $visit->doctor ? [
                                'name'    => $visit->doctor->name,
                                'surname' => $visit->doctor->surname,
                            ] : null,
                        ] : null,
                    ];
                })($lastUserNoteModel)
                : null;

            // Ostatnia notatka szybka (is_edit = true) z TEJ wizyty
            $fastNoteModel = $visit->notes
                ->where('is_edit', true)
                ->sortByDesc('created_at')
                ->first();

            return [
                'visit_id'       => $visit->id,
                'doctor_id'      => optional($visit->doctor)->id,
                'doctor_name'    => optional($visit->doctor)->name,
                'doctor_surname' => optional($visit->doctor)->surname,
                'user_name'      => $user?->name,
                'user_surname'   => $user?->surname,
                'user_type'      => $user?->rodzaj_pacjenta,
                'phone'          => $user?->phone,

                // Dokładnie taki sam format jak w getVisitById()
                'last_user_note' => $lastUserNote,

                'fast_note' => $fastNoteModel
                    ? [
                        'text' => $fastNoteModel->text,
                    ]
                    : null,

                'date'       => $visit->date,
                'start_time' => $visit->start_time,
                'end_time'   => $visit->end_time,
            ];
        });

        return response()->json($result);
    }

    public function getAllVisitsOld(Request $request)
    {
        $week = $request->query('week'); // np. "18.08.2025 - 22.08.2025"

        if (!$week) {
            return response()->json(['error' => 'Brak parametru week'], 400);
        }
        // $user = $visit->user;
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

            // Ostatnia notatka użytkownika z is_edit = false (największy timestamp)
            $lastUserNote = $visit->notes
                ->where('is_edit', false)
                ->sortByDesc('note_date') // sortujemy malejąco po timestampie
                ->first();

            // Pierwsza notatka użytkownika z is_edit = true (najmniejszy timestamp)
            $firstFastNote = $visit->notes
                ->where('is_edit', true)
                ->sortBy('note_date')  // sortujemy rosnąco po timestampie
                ->last();

            return [
                'visit_id' => $visit->id,
                'doctor_id' => $visit->doctor->id,
                'doctor_name' => $visit->doctor->name,
                'doctor_surname' => $visit->doctor->surname,
                'user_name' => $visit->user->name,
                'user_type' => $visit->user->rodzaj_pacjenta,
                'user_surname' => $visit->user->surname,
                'phone' => $visit->user->phone,

                'last_user_note' => $lastUserNote
                    ? [
                        'text' => $lastUserNote->text,
                        'note_date' => $lastUserNote->created_at->format('d.m.Y H:i'), // dokładny timestamp
                        'attachments' => $lastUserNote->attachments,
                    ]
                    : null,

                'fast_note' => $firstFastNote
                    ? [
                        'text' => $firstFastNote->text,
                        // 'note_date' => $firstFastNote->created_at->format('d.m.Y H:i'), // dokładny timestamp
                        // 'attachments' => $firstFastNote->attachments,
                    ]
                    : null,

                'date' => $visit->date,
                'start_time' => $visit->start_time,
                'end_time' => $visit->end_time,
            ];
        });

        return response()->json($result);
    }


    public function reserve(NewVisitRequest $request)
    {
        $validated = $request->validated();

        $reservationStart = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
        $reservationEnd   = $reservationStart->copy()->addMinutes($validated['duration']);

        // 🔹 Sprawdzenia terminów
        if ($reservationEnd->lt(Carbon::now())) {
            return response()->json(['message' => 'Nie można rezerwować zakończonych terminów'], 422);
        }
        if ($reservationStart->isWeekend()) {
            return response()->json(['message' => 'Nie można rezerwować w weekendy'], 422);
        }

        // 🔹 Sprawdzamy urlopy lekarza
        $hasVacation = Vacation::where('doctor_id', $request->doctor_id)
            ->where(function ($q) use ($reservationStart, $reservationEnd) {
                $q->whereRaw('TIMESTAMP(start_date, COALESCE(start_time, "00:00:00")) < ?', [$reservationEnd])
                    ->whereRaw('TIMESTAMP(end_date, COALESCE(end_time, "23:59:59")) > ?', [$reservationStart]);
            })->exists();

        if ($hasVacation) {
            return response()->json(['message' => 'Lekarz jest na urlopie w tym terminie'], 422);
        }

        // 🔹 Sprawdzamy kolizję wizyt
        $exists = Visit::where('doctor_id', $request->doctor_id)
            ->where('date', $reservationStart->toDateString())
            ->where(function ($query) use ($reservationStart, $reservationEnd) {
                $query->where('start_time', '<', $reservationEnd->format('H:i'))
                    ->where('end_time', '>', $reservationStart->format('H:i'));
            })->exists();

        if ($exists) {
            return response()->json([
                'errors' => ['message' => ['Podany termin jest już zajęty']]
            ], 409);
        }

        // 🔹 Obsługa użytkownika
        if (!empty($validated['email'])) {
            // 🔹 Transakcja DB, mail jest warunkiem zapisu wizyty
            try {
                DB::transaction(function () use ($validated, $request, $reservationStart, $reservationEnd) {

                    // Tworzymy / aktualizujemy usera
                    $user = User::firstOrNew(['email' => $validated['email']]);
                    if (!$user->exists) {
                        $user->password = bcrypt('password');
                    }
                    $user->name = $validated['name'];
                    $user->surname = $validated['surname'];
                    $user->phone = $validated['phone'] ?? $user->phone;
                    $user->opis = $validated['opis'] ?? $user->opis;
                    $user->wiek = $validated['wiek'] ?? $user->wiek;
                    $user->save();

                    // Tworzymy wizytę
                    $visit = Visit::create([
                        'doctor_id'  => $request->doctor_id,
                        'type'       => $request->type,
                        'user_id'    => $user->id,
                        'date'       => $reservationStart->toDateString(),
                        'start_time' => $reservationStart->format('H:i'),
                        'end_time'   => $reservationEnd->format('H:i'),
                    ]);

                    // Wysyłamy maila
                    Mail::to($user->email)->send(new VisitConfirmationMail($visit->load('user', 'doctor')));
                });

                return response()->json(['message' => 'Zarezerwowano'], 201);
            } catch (\Exception $e) {
                Log::error("Rezerwacja przerwana – problem z mailem: " . $e->getMessage());
                return response()->json([
                    'errors' => ['email' => ['Nie udało się wysłać maila – wizyta nie została utworzona']]
                ], 422);
            }
        } else {
            // 🔹 Brak emaila → zapisujemy usera i wizytę normalnie
            $user = User::firstOrNew(['phone' => $validated['phone'] ?? null]);
            if (!$user->exists) {
                $user->password = bcrypt('password');
            }
            $user->name = $validated['name'];
            $user->surname = $validated['surname'];
            $user->phone = $validated['phone'] ?? $user->phone;
            $user->opis = $validated['opis'] ?? $user->opis;
            $user->wiek = $validated['wiek'] ?? $user->wiek;
            $user->save();

            $visit = Visit::create([
                'doctor_id'  => $request->doctor_id,
                'type'       => $request->type,
                'user_id'    => $user->id,
                'date'       => $reservationStart->toDateString(),
                'start_time' => $reservationStart->format('H:i'),
                'end_time'   => $reservationEnd->format('H:i'),
            ]);

            return response()->json([
                'message' => 'Zarezerwowano',
                'visit'   => $visit->load('user', 'doctor')
            ], 201);
        }
    }




    public function reserveOld(NewVisitRequest $request)
    {
        $validated = $request->validated();

        $reservationStart = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
        $reservationEnd   = $reservationStart->copy()->addMinutes($validated['duration']);

        if ($reservationEnd->lt(Carbon::now())) {
            return response()->json(['message' => 'Nie można rezerwować zakończonych terminów'], 422);
        }

        if ($reservationStart->isWeekend()) {
            return response()->json(['message' => 'Nie można rezerwować w weekendy'], 422);
        }

        $hasVacation = Vacation::where('doctor_id', $request->doctor_id)
            ->where(function ($q) use ($reservationStart, $reservationEnd) {
                $q->whereRaw('TIMESTAMP(start_date, COALESCE(start_time, "00:00:00")) < ?', [$reservationEnd])
                    ->whereRaw('TIMESTAMP(end_date,   COALESCE(end_time, "23:59:59")) > ?', [$reservationStart]);
            })
            ->exists();

        if ($hasVacation) {
            return response()->json(['message' => 'Lekarz jest na urlopie w tym terminie'], 422);
        }

        $user = User::firstOrCreate(
            ['email' => $request->email],
            [
                'name'     => $request->name,
                'surname'  => $request->surname,
                'phone'    => $request->phone,
                'opis'     => $request->opis,
                'wiek'     => $request->wiek,
                'password' => bcrypt("password"), // zahashowane hasło
            ]
        );

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

        // 🔹 Tworzymy wizytę i zapisujemy w zmiennej
        $visit = Visit::create([
            'doctor_id'  => $request->doctor_id,
            'type'       => $request->type,
            'user_id'    => $user->id,
            'date'       => $reservationStart->toDateString(),
            'start_time' => $reservationStart->format('H:i'),
            'end_time'   => $reservationEnd->format('H:i'),
        ]);

        // 🔹 Wyślij maila z potwierdzeniem
        // 🔹 Wyślij maila tylko jeśli podano email
        if (!empty($visit->user->email)) {
            try {
                Mail::to($visit->user->email)->send(new VisitConfirmationMail($visit));
            } catch (\Exception $e) {
                $validator = Validator::make([], []); // pusty validator
                $validator->errors()->add('email', 'Podany adres e-mail jest błędny');
                return response()->json(['errors' => $validator->errors()], 422);
            }
        }

        // 🔹 Zwracamy cały obiekt wizyty w odpowiedzi
        return response()->json([
            'message' => 'Zarezerwowano',
            // 'visit'   => $visit->load('user', 'doctor') // jeśli chcesz od razu usera i lekarza
        ], 201);
    }


    public function setDoctorWorkingHours(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'working_hours' => 'required|array',
            'working_hours.*.day_of_week' => 'required|integer|min:1|max:7',
            'working_hours.*.start_time' => 'required|date_format:H:i',
            'working_hours.*.end_time' => 'required|date_format:H:i|after:working_hours.*.start_time',
        ]);
        foreach ($request->working_hours as $wh) {
            DoctorWorkingHour::updateOrCreate(
                [
                    'doctor_id' => $request->doctor_id,
                    'day_of_week' => $wh['day_of_week'],
                ],
                [
                    'start_time' => $wh['start_time'],
                    'end_time' => $wh['end_time'],
                ]
            );
        }
        return response()->json(['message' => 'Grafik pracy lekarza zapisany']);
    }

    // public function reserve(NewVisitRequest $request)
    // {

    //     $validated = $request->validated();

    //     $reservationStart = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
    //     $reservationEnd   = $reservationStart->copy()->addMinutes($validated['duration']);


    //     if ($reservationEnd->lt(Carbon::now())) {
    //         return response()->json(['message' => 'Nie można rezerwować zakończonych terminów'], 422);
    //     }


    //     if ($reservationStart->isWeekend()) {
    //         return response()->json(['message' => 'Nie można rezerwować w weekendy'], 422);
    //     }


    //     $hasVacation = Vacation::where('doctor_id', $request->doctor_id)
    //         ->where(function ($q) use ($reservationStart, $reservationEnd) {
    //             $q->whereRaw('TIMESTAMP(start_date, COALESCE(start_time, "00:00:00")) < ?', [$reservationEnd])
    //                 ->whereRaw('TIMESTAMP(end_date,   COALESCE(end_time, "23:59:59")) > ?', [$reservationStart]);
    //         })
    //         ->exists();

    //     if ($hasVacation) {
    //         return response()->json(['message' => 'Lekarz jest na urlopie w tym terminie'], 422);
    //     }


    //     $user = User::firstOrCreate(
    //         ['email' => $request->email],
    //         [
    //             'name'     => $request->name,
    //             'surname'  => $request->surname,
    //             'phone'    => $request->phone,
    //             'opis'    => $request->opis,
    //             'wiek'    => $request->wiek,
    //             'password' => "password", // losowe hasło, zahashowane
    //         ]
    //     );


    //     $exists = Visit::where('doctor_id', $request->doctor_id)
    //         ->where('date', $reservationStart->toDateString())
    //         ->where(function ($query) use ($reservationStart, $reservationEnd) {
    //             $query->where('start_time', '<', $reservationEnd->format('H:i'))
    //                 ->where('end_time',   '>', $reservationStart->format('H:i'));
    //         })
    //         ->exists();

    //     if ($exists) {
    //         return response()->json(['message' => 'Slot już zajęty'], 409);
    //     }


    //     Visit::create([
    //         'doctor_id'  => $request->doctor_id,
    //         'type'  => $request->type,
    //         'user_id'    => $user->id,
    //         'date'       => $reservationStart->toDateString(),
    //         'start_time' => $reservationStart->format('H:i'),
    //         'end_time'   => $reservationEnd->format('H:i'),
    //     ]);

    //     Mail::to($visit->user->email)->send(new VisitConfirmationMail($visit));

    //     return response()->json(['message' => 'Zarezerwowano'], 201);
    // }


    // public function updateVisit(Request $request, $visitId)
    // {
    //     Log::info('Dane requestu przed walidacją:', $request->all());

    //     // Walidacja z przechwyceniem wyjątków
    //     try {
    //         $validated = $request->validate([
    //             'date' => 'required|date',
    //             'hour' => 'required|date_format:H:i',
    //             'doctor_id' => 'required|exists:doctors,id',
    //         ]);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         Log::error('Błąd walidacji:', $e->errors());
    //         return response()->json([
    //             'success' => false,
    //             'errors' => $e->errors()
    //         ], 422);
    //     }

    //     $visit = Visit::find($visitId);
    //     if (!$visit) {
    //         return response()->json(['error' => 'Nie znaleziono wizyty'], 404);
    //     }

    //     $newDoctorId = $validated['doctor_id'];
    //     $newDateTime = Carbon::parse($validated['date'] . ' ' . $validated['hour']);
    //     $newEndTime = $newDateTime->copy()->addHour();

    //     // Sprawdź czy nowy termin nie jest w przeszłości
    //     if ($newDateTime->lt(Carbon::now())) {
    //         return response()->json(['error' => 'Nie można ustawić wizyty w przeszłości'], 422);
    //     }

    //     // Sprawdź czy to nie weekend
    //     if ($newDateTime->isWeekend()) {
    //         return response()->json(['error' => 'Nie można ustawić wizyty w weekendy'], 422);
    //     }

    //     // Sprawdź czy lekarz nie ma urlopu w tym dniu i godzinach
    //     $hasVacation = Vacation::where('doctor_id', $newDoctorId)
    //         ->whereDate('start_date', '<=', $newDateTime->toDateString())
    //         ->whereDate('end_date', '>=', $newDateTime->toDateString())
    //         ->where(function ($q) use ($newDateTime, $newEndTime) {
    //             $q->whereTime('start_time', '<', $newEndTime->format('H:i'))
    //                 ->whereTime('end_time', '>', $newDateTime->format('H:i'));
    //         })
    //         ->exists();

    //     if ($hasVacation) {
    //         return response()->json(['error' => 'Lekarz jest na urlopie w tym czasie'], 422);
    //     }

    //     // Sprawdź czy slot jest wolny (pomijając obecną wizytę)
    //     $exists = Visit::where('doctor_id', $newDoctorId)
    //         ->whereDate('date', $validated['date'])
    //         ->whereTime('start_time', '<', $newEndTime->format('H:i'))
    //         ->whereTime('end_time', '>', $newDateTime->format('H:i'))
    //         ->where('id', '!=', $visit->id)
    //         ->exists();

    //     if ($exists) {
    //         return response()->json(['error' => 'Slot jest już zajęty'], 409);
    //     }

    //     // Aktualizacja wizyty
    //     $visit->doctor_id = $newDoctorId;
    //     $visit->date = $validated['date'];
    //     $visit->start_time = $validated['hour'];
    //     $visit->end_time = $newEndTime;
    //     $visit->save();

    //     Log::info('Wizyta zaktualizowana:', $visit->toArray());

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Wizyta zaktualizowana',
    //         'request' => $validated,
    //         'visit' => $visit
    //     ]);
    // }

    // public function updateVisit(Request $request, $visitId)
    // {
    //     Log::info('Dane requestu przed walidacją:', $request->all());

    //     // Walidacja
    //     try {
    //         $validated = $request->validate([
    //             'date' => 'required|date',
    //             'hour' => 'required|date_format:H:i',
    //             'doctor_id' => 'required|exists:doctors,id',
    //         ]);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         Log::error('Błąd walidacji:', $e->errors());
    //         return response()->json([
    //             'success' => false,
    //             'errors' => $e->errors()
    //         ], 422);
    //     }

    //     $visit = Visit::find($visitId);
    //     if (!$visit) {
    //         return response()->json(['error' => 'Nie znaleziono wizyty'], 404);
    //     }

    //     // kopiujemy stare dane wizyty do maila
    //     $oldVisit = clone $visit;

    //     $newDoctorId = $validated['doctor_id'];
    //     $newDateTime = Carbon::parse($validated['date'] . ' ' . $validated['hour']);

    //     // 🔹 Zachowujemy długość starej wizyty
    //     $oldDuration = Carbon::parse($visit->end_time)->diffInMinutes(Carbon::parse($visit->start_time));
    //     $newEndTime = $newDateTime->copy()->addMinutes($oldDuration);

    //     // Sprawdź czy nowy termin nie jest w przeszłości
    //     if ($newDateTime->lt(Carbon::now())) {
    //         return response()->json(['error' => 'Nie można ustawić wizyty w przeszłości'], 422);
    //     }

    //     // Sprawdź czy to nie weekend
    //     if ($newDateTime->isWeekend()) {
    //         return response()->json(['error' => 'Nie można ustawić wizyty w weekendy'], 422);
    //     }

    //     // Sprawdź czy lekarz nie ma urlopu w tym dniu i godzinach
    //     $hasVacation = Vacation::where('doctor_id', $newDoctorId)
    //         ->whereDate('start_date', '<=', $newDateTime->toDateString())
    //         ->whereDate('end_date', '>=', $newDateTime->toDateString())
    //         ->where(function ($q) use ($newDateTime, $newEndTime) {
    //             $q->whereTime('start_time', '<', $newEndTime->format('H:i'))
    //                 ->whereTime('end_time', '>', $newDateTime->format('H:i'));
    //         })
    //         ->exists();

    //     if ($hasVacation) {
    //         return response()->json(['error' => 'Lekarz jest na urlopie w tym czasie'], 422);
    //     }

    //     // Sprawdź czy slot jest wolny (pomijając obecną wizytę)
    //     $exists = Visit::where('doctor_id', $newDoctorId)
    //         ->whereDate('date', $validated['date'])
    //         ->whereTime('start_time', '<', $newEndTime->format('H:i'))
    //         ->whereTime('end_time', '>', $newDateTime->format('H:i'))
    //         ->where('id', '!=', $visit->id)
    //         ->exists();

    //     if ($exists) {
    //         return response()->json(['error' => 'Slot jest już zajęty'], 409);
    //     }

    //     // Aktualizacja wizyty
    //     $visit->doctor_id = $newDoctorId;
    //     $visit->date = $validated['date'];
    //     $visit->start_time = $validated['hour'];
    //     $visit->end_time = $newEndTime;
    //     $visit->save();

    //     Log::info('Wizyta zaktualizowana:', $visit->toArray());

    //     // 🔹 Wyślij maila o zmianie terminu
    //     Mail::to($visit->user->email)->send(new VisitRescheduledSimpleMail($oldVisit, $visit));

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Wizyta zaktualizowana i wysłano powiadomienie e-mail',
    //         'request' => $validated,
    //         'visit' => $visit
    //     ]);
    // }

    public function updateVisit(Request $request, $visitId)
    {
        Log::info('Dane requestu przed walidacją:', $request->all());

        // Walidacja
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'hour' => 'required|date_format:H:i',
                'doctor_id' => 'required|exists:doctors,id',
                'duration' => 'nullable|integer|min:1', // 🔹 opcjonalne
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

        // kopiujemy stare dane wizyty do maila
        $oldVisit = clone $visit;

        $newDoctorId = $validated['doctor_id'];
        $newDateTime = Carbon::parse($validated['date'] . ' ' . $validated['hour']);

        // 🔹 Używamy duration z request jeśli podane, inaczej długość starej wizyty
        $oldDuration = Carbon::parse($visit->end_time)->diffInMinutes(Carbon::parse($visit->start_time));
        $duration = $validated['duration'] ?? $oldDuration;
        $newEndTime = $newDateTime->copy()->addMinutes($duration);

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

        // 🔹 Wyślij maila o zmianie terminu
        // Mail::to($visit->user->email)->send(new VisitRescheduledSimpleMail($oldVisit, $visit));

        if ($visit->user && !empty($visit->user->email)) {
            Mail::to($visit->user->email)->send(new VisitRescheduledSimpleMail($oldVisit, $visit));
            Log::info("Wysłano e-mail o zmianie wizyty do {$visit->user->email}");
        } else {
            Log::warning("Nie wysłano e-maila – brak adresu dla użytkownika ID: {$visit->user_id}");
        }

        return response()->json([
            'success' => true,
            'message' => 'Wizyta zaktualizowana',
            // 'request' => $validated,
            'visit' => $visit
        ]);
    }



    public function allUsers(Request $request)
    {
        $limit = (int) $request->get('limit', 100);
        $offset = (int) $request->get('offset', 0);
        $search = trim($request->get('search', ''));

        // 🔤 Upewnij się, że zapytania używają polskiego porównywania znaków
        DB::statement("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_polish_ci'");

        $baseQuery = User::select('id', 'name', 'surname');

        // 🔍 WYSZUKIWANIE — od początku imienia / nazwiska + wsparcie dla "cyprian wac"
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                // Rozdziel frazę po spacji
                $parts = preg_split('/\s+/', $search);
                $first = $parts[0] ?? '';
                $second = $parts[1] ?? '';

                // Dopasowanie: "imię nazwisko"
                $q->where(function ($q2) use ($first, $second) {
                    $q2->where('name', 'like', "{$first}%")
                        ->when($second, fn($qq) => $qq->where('surname', 'like', "{$second}%"));
                })

                    // Lub samo imię/nazwisko zaczynające się od wpisanej frazy
                    ->orWhere(function ($q3) use ($search) {
                        $q3->where('name', 'like', "{$search}%")
                            ->orWhere('surname', 'like', "{$search}%");
                    });
            });
        }

        // 📊 Zlicz wszystkich pasujących użytkowników (bez offset/limit)
        $totalCount = $baseQuery->count();

        // 📚 Pobierz użytkowników z limitem i offsetem, sortując po polsku
        $users = $baseQuery
            ->orderByRaw("CONVERT(name USING utf8mb4) COLLATE utf8mb4_polish_ci ASC")
            ->offset($offset)
            ->limit($limit)
            ->get();

        $nextOffset = $offset + $limit;
        $hasMore = $nextOffset < $totalCount;

        // 🅰️ Grupowanie po pierwszej literze z obsługą polskich znaków
        $grouped = $users->groupBy(function ($user) {
            return mb_strtoupper(mb_substr($user->name, 0, 1, 'UTF-8'), 'UTF-8');
        });

        return response()->json([
            'data' => $grouped,
            'hasMore' => $hasMore,
            'nextOffset' => $nextOffset,
        ]);
    }


    public function allUsersOld(Request $request)
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

        $notes = $user->notes()
            ->get()
            ->where('is_edit', false)
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
            'phone'   => $user->phone,
            'pesel'   => $user->pesel,
            'city'   => $user->city,
            'city_code'   => $user->city_code,
            'street'   => $user->street,
            'age'   => $user->age_with_suffix,
            'description'   => $user->opis,
            'patient_type'   => $user->rodzaj_pacjenta,
            // 'visits'  => $visits,
            'visits'   => $notes
        ]);
    }

    public function updateUserData(UpdatePatientRequest $request, $id)
    {
        $user = User::findOrFail($id);

        // Aktualizacja na podstawie zwalidowanych danych
        $user->update($request->validated());

        return response()->json([
            'message' => 'User updated successfully',
            'user'    => $user
        ]);
    }

    public function addPatient(StorePatientRequest $request)
    {
        $user = User::create([
            'name'     => $request->name,
            'surname'  => $request->surname,
            'phone'    => $request->phone,
            'email'    => $request->email,
            'password' => "password",
            'wiek'    => $request->wiek,
            'opis'    => $request->opis,
            'rodzaj_pacjenta'    => $request->rodzaj_pacjenta,
            'city_code' => $request->city_code,
            'city' => $request->city,
            'street' => $request->street,
            'pesel' => $request->pesel,
        ]);

        if ($request->description) {
            $user->description = $request->description;
            $user->save();
        }

        return response()->json([
            'message' => 'Pacjent dodany pomyślnie',
            'user'    => $user
        ], 201);
    }

    public function deletePatient(int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Pacjent nie został znaleziony'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'message' => 'Pacjent został usunięty pomyślnie'
        ], 200);
    }


    // Anulowanie wizyty
    public function cancel($id)
    {
        $visit = Visit::find($id);

        if (!$visit) {
            return response()->json(['message' => 'Nie znaleziono rezerwacji'], 404);
        }

        // Wyślij mail tylko jeśli user ma email
        if ($visit->user && !empty($visit->user->email)) {
            Mail::to($visit->user->email)->send(new VisitCancelledMail($visit));
        }

        $visit->delete();

        return response()->json(['message' => 'Anulowano i wysłano powiadomienie e-mail']);
    }

    public function loginDoctor(DoctorLoginRequest $request)
    {
        $doctor = Doctor::where('email', $request->email)->first();

        if (!$doctor || !Hash::check($request->password, $doctor->password)) {
            return response()->json([
                'errors' => [
                    'email' => [" "],
                    'password' => [" "],
                    'message' => 'Nieprawidłowy email lub hasło',
                ]
            ], 401);
        }

        // Jeśli używasz Sanctum (polecam):
        $token = $doctor->createToken('doctor_token')->plainTextToken;

        return response()->json([
            'message' => 'Zalogowano pomyślnie.',
            'doctor' => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'email' => $doctor->email,
            ],
            'token' => $token,
        ], 200);
    }

    public function showUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            abort(404, 'User not found');
        }

        return response()->json($user);
    }



    public function showVisit($id)
    {
        $visit = Visit::find($id);

        if (!$visit) {
            abort(404, 'Visit not found');
        }
        $mappedVisit = [
            'date' => Carbon::parse($visit->date)->format('d.m.Y'),
            'start_time' => Carbon::parse($visit->start_time)->format('H:i'),
            'end_time' => Carbon::parse($visit->end_time)->format('H:i'),
            // 'id'        => $visit->id,
            'type'        => $visit->type,
            'duration'        => $visit->duration,
            // 'user_name' => $visit->user->name ?? null, // jeśli relacja z userem istnieje
            // 'date'      => $visit->date,
            // 'time'      => $visit->start_time . ' - ' . $visit->end_time,
            'doctor'    => $visit->doctor_id,
        ];

        return response()->json($mappedVisit);
    }

    // public function getDoctorWorkingHours(Request $request)
    // {
    //     $allWorkHour = DoctorWorkingHour::select('doctor_id', 'day_of_week', 'start_time', 'end_time')
    //         ->orderBy('doctor_id')
    //         ->orderBy('day_of_week')
    //         ->get()
    //         ->groupBy('doctor_id');

    //     $result = $allWorkHour->map(function ($hours, $doctorId) {
    //         return $hours->map(function ($hour) {
    //             return [
    //                 'day_of_week' => $hour->day_of_week,
    //                 'start_time'  => Carbon::parse($hour->start_time)->format('H:i'),
    //                 'end_time'    => Carbon::parse($hour->end_time)->format('H:i'),
    //             ];
    //         })->values();
    //     });

    //     return response()->json($result);
    // }

    public function getDoctorWorkingHours()
    {
        $hours = DoctorWorkingHour::with('doctor')->get();

        $daysMap = [
            1 => 'Poniedziałek',
            2 => 'Wtorek',
            3 => 'Środa',
            4 => 'Czwartek',
            5 => 'Piątek',
            // 6 => 'Sobota',
            // 7 => 'Niedziela',
        ];

        $grouped = [];

        foreach ($hours as $hour) {
            $doctorId   = $hour->doctor->id;
            $doctorName = "{$hour->doctor->name} {$hour->doctor->surname}";

            // Jeśli tego lekarza jeszcze nie ma w grupie – dodaj pustą strukturę
            if (!isset($grouped[$doctorId])) {
                $grouped[$doctorId] = [
                    'doctor_id'   => $doctorId,
                    'doctor_name' => $doctorName,
                    'hours'       => [],
                ];
            }

            $grouped[$doctorId]['hours'][] = [
                'day'        => $daysMap[$hour->day_of_week] ?? $hour->day_of_week,
                'start_time' => \Carbon\Carbon::parse($hour->start_time)->format('H:i'),
                'end_time'   => \Carbon\Carbon::parse($hour->end_time)->format('H:i'),
            ];
        }

        // Posortuj po ID lekarza (opcjonalnie)
        ksort($grouped);

        // Zwróć posortowane wartości jako tablicę indeksowaną
        return response()->json(array_values($grouped));
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
        $daysAhead = $request->input('days_ahead') ?? 20; // domyślnie 20 dni

        $result = [];
        $doctor = Doctor::findOrFail($doctorId);

        for ($i = 0; $i < $daysAhead; $i++) {
            $date = $startDate->copy()->addDays($i);

            // Pomijamy weekendy
            if ($date->isWeekend()) {
                continue;
            }

            // Generujemy wszystkie możliwe sloty dnia (bez uwzględnienia wizyt/przerw)
            $workStart = Carbon::parse($date->toDateString() . ' 7:30');
            $workEnd = Carbon::parse($date->toDateString() . ' 21:00');
            $allSlots = [];
            $current = $workStart->copy();
            while ($current->lt($workEnd)) {
                $slotEnd = $current->copy()->addMinutes(45);
                if ($slotEnd->gt($workEnd)) break;
                $allSlots[] = $current->format('H:i');
                $current->addMinutes(45);
            }

            // Pobieramy wolne sloty dnia
            $freeSlots = $this->generateDailySlots($doctor->id, $date);

            // Upewniamy się, że format jest taki sam
            $freeSlots = array_map(fn($s) => Carbon::parse($s)->format('H:i'), $freeSlots);

            // Jeśli wszystkie sloty są wolne, dodajemy dzień do wyniku
            if (!empty($allSlots) && count(array_intersect($allSlots, $freeSlots)) === count($allSlots)) {
                $result[] = [
                    'value' => $date->toDateString(),
                    'label' => $date->format('d.m.Y'),
                ];
            }
        }

        return response()->json($result);
    }

public function updateDoctorWorkingHours(Request $request, $doctorId)
{
    // Sprawdzamy, czy tablica hours istnieje
    $request->validate([
        'hours' => 'required|array',
        'hours.*.start_time' => 'required|date_format:H:i',
        'hours.*.end_time' => 'required|date_format:H:i|after:start_time',
        'hours.*.day' => 'required|string',
    ]);

    $daysMap = [
        'Poniedziałek' => 1,
        'Wtorek' => 2,
        'Środa' => 3,
        'Czwartek' => 4,
        'Piątek' => 5,
        'Sobota' => 6,
        'Niedziela' => 7,
    ];

    $hours = $request->input('hours');

    foreach ($hours as $hour) {
        // zamiana nazwy dnia na numer
        $dayNumber = $daysMap[$hour['day']] ?? null;
        if (!$dayNumber) continue; // jeśli dzień niepoprawny, pomijamy

        // Szukamy istniejącego rekordu po doctor_id i day_of_week
        $workingHour = \App\Models\DoctorWorkingHour::where('doctor_id', $doctorId)
            ->where('day_of_week', $dayNumber)
            ->first();

        if ($workingHour) {
            // Aktualizacja
            $workingHour->update([
                'start_time' => $hour['start_time'],
                'end_time' => $hour['end_time'],
            ]);
        } else {
            // Tworzymy nowy rekord
            DoctorWorkingHour::create([
                'doctor_id' => $doctorId,
                'day_of_week' => $dayNumber,
                'start_time' => $hour['start_time'],
                'end_time' => $hour['end_time'],
            ]);
        }
    }

    return response()->json(['message' => 'Godziny pracy zaktualizowane']);
}

    public function deleteDoctorWorkingHour($id)
    {
        $workingHour = \App\Models\DoctorWorkingHour::findOrFail($id);
        $workingHour->delete();
        return response()->json(['message' => 'Godziny pracy usunięte']);
    }
}
