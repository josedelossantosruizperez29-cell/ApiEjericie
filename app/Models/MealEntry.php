<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealEntry extends Model
{
    use HasUuids;

    protected $table = 'meal_entries';

    protected function casts(): array
    {
        return [
            'eaten_at' => 'immutable_datetime',
            'local_date' => 'immutable_date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mealEntryItems(): HasMany
    {
        return $this->hasMany(MealEntryItem::class, 'meal_entry_id');
    }
}
