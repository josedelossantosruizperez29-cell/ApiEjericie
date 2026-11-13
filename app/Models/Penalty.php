<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penalty extends Model
{
    use HasUuids;

    protected $table = 'penalties';

    protected function casts(): array
    {
        return [
            'missed_on' => 'immutable_date',
            'activated_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function originSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'origin_session_id');
    }

    public function resolvedSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'resolved_session_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PenaltyRule::class, 'rule_id');
    }
}
