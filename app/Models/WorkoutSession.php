<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkoutSession extends Model
{
    use HasUuids;

    protected $table = 'workout_sessions';

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'immutable_date',
            'cutoff_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trainingPlan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'rescheduled_from_id');
    }

    public function workoutSessionsRescheduledFrom(): HasMany
    {
        return $this->hasMany(WorkoutSession::class, 'rescheduled_from_id');
    }

    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class, 'workout_session_id');
    }

    public function penaltyOriginSession(): HasOne
    {
        return $this->hasOne(Penalty::class, 'origin_session_id');
    }

    public function penaltyResolvedSession(): HasOne
    {
        return $this->hasOne(Penalty::class, 'resolved_session_id');
    }

    public function streakEvents(): HasMany
    {
        return $this->hasMany(StreakEvent::class, 'workout_session_id');
    }
}
