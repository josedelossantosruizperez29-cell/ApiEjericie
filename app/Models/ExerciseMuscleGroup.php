<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseMuscleGroup extends Model
{
    use HasUuids;

    protected $table = 'exercise_muscle_groups';

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    public function muscleGroup(): BelongsTo
    {
        return $this->belongsTo(MuscleGroup::class, 'muscle_group_id');
    }
}
