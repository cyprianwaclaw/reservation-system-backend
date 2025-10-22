<?php

// namespace App\Models;

// use Illuminate\Database\Eloquent\Model;

// class DoctorSlot extends Model
// {
//     protected $fillable = [
//         'doctor_id',
//         'date',
//         'start_time',
//         'end_time',
//         'type',
//         'visit_id',
//     ];

//     // W modelu DoctorSlot
//     protected $casts = [
//         'date' => 'immutable_date:Y-m-d',
//     ];

//     protected function serializeDate(\DateTimeInterface $date): string
//     {
//         return $date->format('Y-m-d');
//     }

//     public function doctor()
//     {
//         return $this->belongsTo(Doctor::class);
//     }

//     public function visit()
//     {
//         return $this->belongsTo(Visit::class);
//     }
// }<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DoctorSlot extends Model
{
    protected $fillable = [
        'doctor_id',
        'date',
        'start_time',
        'end_time',
        'type',
        'visit_id',
    ];

    protected $casts = [
        'date' => 'immutable_date', // CarbonImmutable
    ];

    // Zwracamy jednolity format daty dla JSON
    public function getDateAttribute($value)
    {
        return Carbon::parse($value)->format('Y-m-d');
    }

    // Zwracamy jednolity format godzin dla JSON
    public function getStartTimeAttribute($value)
    {
        return Carbon::parse($value)->format('H:i');
    }

    public function getEndTimeAttribute($value)
    {
        return Carbon::parse($value)->format('H:i');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }
}
