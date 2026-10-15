<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDocument extends Model
{
    protected $fillable = ['student_id', 'type', 'document_number', 'issued_on', 'expires_on', 'status', 'notes'];

    protected $casts = ['issued_on' => 'date:Y-m-d', 'expires_on' => 'date:Y-m-d'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
