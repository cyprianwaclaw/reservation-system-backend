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
}
