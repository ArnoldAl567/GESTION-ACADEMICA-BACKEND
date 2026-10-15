<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competency extends Model
{
    protected $fillable = ['course_id', 'grade', 'name', 'capacity', 'performance', 'evaluation_criterion', 'status'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
