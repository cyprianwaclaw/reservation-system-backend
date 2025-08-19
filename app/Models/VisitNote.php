<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitNote extends Model
{
protected $fillable = [
'visit_id',
'note_date',
'text',
'attachments'
];

protected $casts = [
'attachments' => 'array',
'note_date' => 'date'
];

public function visit()
{
return $this->belongsTo(Visit::class);
}
}
