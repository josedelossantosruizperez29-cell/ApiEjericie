<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiGeneration extends Model
{
    use HasUuids;

    protected $table = 'ai_generations';

    protected function casts(): array
    {
        return [
            'requested_at' => 'immutable_datetime',
            'response' => 'array',
            'usage' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aiPlanRecommendations(): HasMany
    {
        return $this->hasMany(AiPlanRecommendation::class, 'generation_id');
    }
}
