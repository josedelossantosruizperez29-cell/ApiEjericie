<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingPlanRevision extends Model
{
    use HasUuids;

    protected $table = 'training_plan_revisions';

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'changes' => 'array',
        ];
    }

    public function trainingPlan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }
}
