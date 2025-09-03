<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'surname',
        'phone',
        'email',
        'password',
        'wiek',
        'opis',
        'rodzaj_pacjenta',
        'city_code',
        'city',
        'street',
        'pesel',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function notes()
    {
        return $this->hasManyThrough(VisitNote::class, Visit::class);
    }
    public function getAgeWithSuffixAttribute(): ?string
    {
        $age = $this->wiek;

        if (is_null($age)) {
            return ''; // brak wieku → nic nie zwracamy
        }

        if ($age === 1) {
            return $age . ' rok';
        }

        if ($age % 10 >= 2 && $age % 10 <= 4 && !($age % 100 >= 12 && $age % 100 <= 14)) {
            return $age . ' lata';
        }

        return $age . ' lat';
    }
}
