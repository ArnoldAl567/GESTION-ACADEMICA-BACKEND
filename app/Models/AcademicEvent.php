<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicEvent extends Model
{
    protected $fillable = ['academic_year_id', 'classroom_id', 'title', 'type', 'starts_at', 'ends_at', 'description', 'status'];

    protected $casts = ['starts_at' => 'datetime:Y-m-d\TH:i', 'ends_at' => 'datetime:Y-m-d\TH:i'];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
