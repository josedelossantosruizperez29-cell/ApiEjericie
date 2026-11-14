<?php

namespace App\Services;

use App\Models\FoodItem;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class NutritionTotals
{
    public function portion(?FoodItem $food, float $quantity): array
    {
        $values = [];
        foreach (['kcal', 'protein_g', 'carbs_g', 'fat_g'] as $field) {
            $values[$field] = $food ? round($food->$field * $quantity / $food->reference_quantity, 3) : null;
        }

        return $values;
    }

    public function forDay(User $user, string $localDate): object
    {
        return $this->totals($user)->where('meal_entries.local_date', $localDate)->first();
    }

    public function forMeal(User $user, string $mealId): object
    {
        $meal = $user->mealEntries()->findOrFail($mealId);

        return $this->totals($user)->where('meal_entries.id', $meal->id)->first();
    }

    public function history(User $user)
    {
        return $this->totals($user)->addSelect('meal_entries.local_date')->groupBy('meal_entries.local_date')->orderByDesc('meal_entries.local_date')->paginate(30);
    }

    private function totals(User $user): Builder
    {
        return DB::table('meal_entries')->join('meal_entry_items as item', 'item.meal_entry_id', '=', 'meal_entries.id')
            ->where('meal_entries.user_id', $user->id)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('meal_entry_items as correction')
                ->whereColumn('correction.supersedes_id', 'item.id'))
            ->selectRaw('CASE WHEN COUNT(item.id)=COUNT(item.kcal) THEN COALESCE(SUM(item.kcal),0) END AS kcal, CASE WHEN COUNT(item.id)=COUNT(item.protein_g) THEN COALESCE(SUM(item.protein_g),0) END AS protein_g,
                CASE WHEN COUNT(item.id)=COUNT(item.carbs_g) THEN COALESCE(SUM(item.carbs_g),0) END AS carbs_g, CASE WHEN COUNT(item.id)=COUNT(item.fat_g) THEN COALESCE(SUM(item.fat_g),0) END AS fat_g');
    }
}
