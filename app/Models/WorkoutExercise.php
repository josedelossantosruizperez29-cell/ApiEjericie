<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkoutExercise extends Model
{
    use HasUuids;

    protected $table = 'workout_exercises';

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'sets' => 'integer',
            'base_reps' => 'integer',
            'rest_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'distance_m' => 'decimal:3',
            'load_kg' => 'decimal:3',
            'prescription' => 'array',
        ];
    }

    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    public function exerciseSetLogs(): HasMany
    {
        return $this->hasMany(ExerciseSetLog::class, 'workout_exercise_id');
    }

    public function workoutPenaltyApplication(): HasOne
    {
        return $this->hasOne(WorkoutPenaltyApplication::class, 'workout_exercise_id');
    }
}
