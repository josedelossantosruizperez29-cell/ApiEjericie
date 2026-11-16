<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    use HasUuids;

    protected $table = 'exercises';

    protected $attributes = ['image_url' => null];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'penalty_eligible' => 'boolean',
            'rep_limits' => 'array',
        ];
    }

    public function lowerImpact(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'lower_impact_id');
    }

    public function exercisesLowerImpact(): HasMany
    {
        return $this->hasMany(Exercise::class, 'lower_impact_id');
    }

    public function exerciseMuscleGroups(): HasMany
    {
        return $this->hasMany(ExerciseMuscleGroup::class, 'exercise_id');
    }

    public function exerciseEquipment(): HasMany
    {
        return $this->hasMany(ExerciseEquipment::class, 'exercise_id');
    }

    public function userHealthConstraints(): HasMany
    {
        return $this->hasMany(UserHealthConstraint::class, 'exercise_id');
    }

    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class, 'exercise_id');
    }

    public function requiredEquipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'exercise_equipment');
    }

    public function muscleGroups(): BelongsToMany
    {
        return $this->belongsToMany(MuscleGroup::class, 'exercise_muscle_groups');
    }
}
