<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MuscleGroup extends Model
{
    use HasUuids;

    protected $table = 'muscle_groups';

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MuscleGroup::class, 'parent_id');
    }

    public function muscleGroupsParent(): HasMany
    {
        return $this->hasMany(MuscleGroup::class, 'parent_id');
    }

    public function exerciseMuscleGroups(): HasMany
    {
        return $this->hasMany(ExerciseMuscleGroup::class, 'muscle_group_id');
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'exercise_muscle_groups');
    }
}
