<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentObservation extends Model
{
    protected $fillable = ['enrollment_id', 'academic_period_id', 'type', 'observation', 'observed_on', 'recorded_by'];

    protected $casts = ['observed_on' => 'date:Y-m-d'];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}
