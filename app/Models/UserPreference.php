<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasUuids;

    protected $table = 'user_preferences';

    protected function casts(): array
    {
        return [
            'penalty_adjustments' => 'boolean',
            'recovery' => 'boolean',
            'max_extra_reps' => 'integer',
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
