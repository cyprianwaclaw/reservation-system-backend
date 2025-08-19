<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vacation extends Model
{
    protected $fillable = [
        'doctor_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}

