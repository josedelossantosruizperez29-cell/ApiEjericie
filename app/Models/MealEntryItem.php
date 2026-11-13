<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MealEntryItem extends Model
{
    use HasUuids;

    protected $table = 'meal_entry_items';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'grams' => 'decimal:3',
            'kcal' => 'decimal:3',
            'protein_g' => 'decimal:3',
            'carbs_g' => 'decimal:3',
            'fat_g' => 'decimal:3',
            'estimated_at' => 'immutable_datetime',
            'confidence' => 'decimal:3',
        ];
    }

    public function mealEntry(): BelongsTo
    {
        return $this->belongsTo(MealEntry::class, 'meal_entry_id');
    }

    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class, 'food_item_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(MealEntryItem::class, 'supersedes_id');
    }

    public function mealEntryItemSupersedes(): HasOne
    {
        return $this->hasOne(MealEntryItem::class, 'supersedes_id');
    }
}
