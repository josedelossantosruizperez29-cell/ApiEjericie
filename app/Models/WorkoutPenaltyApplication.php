<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutPenaltyApplication extends Model
{
    use HasUuids;

    protected $table = 'workout_penalty_applications';

    protected function casts(): array
    {
        return [
            'pending_count' => 'integer',
            'original' => 'array',
            'applied' => 'array',
        ];
    }

    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class, 'workout_exercise_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PenaltyRule::class, 'rule_id');
    }
}
