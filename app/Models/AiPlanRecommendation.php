<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPlanRecommendation extends Model
{
    use HasUuids;

    protected $table = 'ai_plan_recommendations';

    protected function casts(): array
    {
        return [
            'recommendation' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'generation_id');
    }

    public function trainingPlan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }
}
