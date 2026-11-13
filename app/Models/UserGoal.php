<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserGoal extends Model
{
    use HasUuids;

    protected $table = 'user_goals';

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'priority' => 'integer',
            'target_value' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trainingPlansGoal(): HasMany
    {
        return $this->hasMany(TrainingPlan::class, 'goal_id');
    }
}
