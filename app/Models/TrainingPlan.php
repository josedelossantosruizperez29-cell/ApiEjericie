<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingPlan extends Model
{
    use HasUuids;

    protected $table = 'training_plans';

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(UserGoal::class, 'goal_id');
    }

    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class, 'training_plan_id');
    }

    public function trainingPlanRevisions(): HasMany
    {
        return $this->hasMany(TrainingPlanRevision::class, 'training_plan_id');
    }

    public function aiPlanRecommendations(): HasMany
    {
        return $this->hasMany(AiPlanRecommendation::class, 'training_plan_id');
    }
}
