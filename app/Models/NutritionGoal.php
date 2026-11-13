<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionGoal extends Model
{
    use HasUuids;

    protected $table = 'nutrition_goals';

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'kcal' => 'decimal:3',
            'protein_g' => 'decimal:3',
            'carbs_g' => 'decimal:3',
            'fat_g' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
