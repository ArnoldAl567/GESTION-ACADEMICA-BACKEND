<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministrativeStaff extends Model
{
    protected $table = 'administrative_staff';

    protected $fillable = ['user_id', 'name', 'dni', 'email', 'phone', 'position', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
