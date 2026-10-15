<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['code', 'name', 'description', 'scope', 'icon', 'active', 'system'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'system' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'code');
    }
}
