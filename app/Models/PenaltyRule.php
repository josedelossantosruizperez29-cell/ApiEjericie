<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenaltyRule extends Model
{
    use HasUuids;

    protected $table = 'penalty_rules';

    protected function casts(): array
    {
        return [
            'increment_reps' => 'integer',
            'max_extra_reps' => 'integer',
            'active' => 'boolean',
            'adjustments_enabled' => 'boolean',
            'conditions' => 'array',
        ];
    }

    public function penaltiesRule(): HasMany
    {
        return $this->hasMany(Penalty::class, 'rule_id');
    }

    public function workoutPenaltyApplicationsRule(): HasMany
    {
        return $this->hasMany(WorkoutPenaltyApplication::class, 'rule_id');
    }
}
