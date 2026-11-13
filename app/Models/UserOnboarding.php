<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOnboarding extends Model
{
    use HasUuids;

    protected $table = 'user_onboarding';

    protected function casts(): array
    {
        return [
            'step' => 'integer',
            'answers' => 'array',
            'weekly_availability' => 'array',
            'session_minutes' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
