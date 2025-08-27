<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    protected $fillable = [
        'doctor_id',
        'user_id',
        'date',
        'type',
        'start_time',
        'end_time',
    ];

    // Relacje (opcjonalnie)
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notes()
    {
        return $this->hasMany(VisitNote::class);
    }

}