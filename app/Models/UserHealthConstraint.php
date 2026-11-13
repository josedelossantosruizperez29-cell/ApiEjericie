<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHealthConstraint extends Model
{
    use HasUuids;

    protected $table = 'user_health_constraints';

    protected $hidden = ['description', 'notes', 'avoided_movements'];

    protected function casts(): array
    {
        return [
            'avoided_movements' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }
}
