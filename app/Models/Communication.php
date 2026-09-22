<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Communication extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'subject', 'body', 'audience', 'classroom_id', 'author_id', 'draft', 'published_at'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['read_at', 'archived']);
    }

    protected function casts(): array
    {
        return ['draft' => 'boolean', 'published_at' => 'datetime'];
    }
}
