<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class NutritionTotals
{
    public function forDay(User $user, string $localDate): object
    {
        return $this->totals($user)->where('meal_entries.local_date', $localDate)->first();
    }

    public function forMeal(User $user, string $mealId): object
    {
        $meal = $user->mealEntries()->findOrFail($mealId);

        return $this->totals($user)->where('meal_entries.id', $meal->id)->first();
    }

    private function totals(User $user): Builder
    {
        return DB::table('meal_entries')->join('meal_entry_items as item', 'item.meal_entry_id', '=', 'meal_entries.id')
            ->where('meal_entries.user_id', $user->id)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('meal_entry_items as correction')
                ->whereColumn('correction.supersedes_id', 'item.id'))
            ->selectRaw('COALESCE(SUM(item.kcal),0) AS kcal, COALESCE(SUM(item.protein_g),0) AS protein_g,
                COALESCE(SUM(item.carbs_g),0) AS carbs_g, COALESCE(SUM(item.fat_g),0) AS fat_g');
    }
}
