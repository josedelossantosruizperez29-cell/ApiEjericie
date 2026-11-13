<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseSetLog extends Model
{
    use HasUuids;

    protected $table = 'exercise_set_logs';

    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'reps' => 'integer',
            'duration_seconds' => 'integer',
            'distance_m' => 'decimal:3',
            'load_kg' => 'decimal:3',
            'perceived_effort' => 'integer',
            'logged_at' => 'immutable_datetime',
        ];
    }

    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class, 'workout_exercise_id');
    }
}
