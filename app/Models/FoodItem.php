<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodItem extends Model
{
    use HasUuids;

    protected $table = 'food_items';

    protected function casts(): array
    {
        return [
            'reference_quantity' => 'decimal:3',
            'kcal' => 'decimal:3',
            'protein_g' => 'decimal:3',
            'carbs_g' => 'decimal:3',
            'fat_g' => 'decimal:3',
            'source_date' => 'immutable_date',
            'confidence' => 'decimal:3',
        ];
    }

    public function mealEntryItems(): HasMany
    {
        return $this->hasMany(MealEntryItem::class, 'food_item_id');
    }
}
