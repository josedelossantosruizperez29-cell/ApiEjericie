<?php

namespace App\Services;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\MealEntryItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MealJournal
{
    public function create(User $user, array $data): MealEntry
    {
        $zone = $user->userProfile?->timezone ?? 'UTC';
        $time = CarbonImmutable::parse($data['eaten_at'], $zone);

        return $user->mealEntries()->save((new MealEntry)->forceFill(['local_date' => $time->setTimezone($zone)->toDateString(), 'timezone' => $zone, 'eaten_at' => $time->utc()] + $data));
    }

    public function portion(User $user, string $id, array $data, ?string $itemId = null): MealEntryItem
    {
        return DB::transaction(function () use ($user, $id, $data, $itemId) {
            $meal = $user->mealEntries()->lockForUpdate()->findOrFail($id);
            if ($itemId) {
                $old = $meal->mealEntryItems()->findOrFail($itemId);
                if ($meal->mealEntryItems()->where('supersedes_id', $old->id)->exists()) {
                    throw new \DomainException('Esta porción ya fue corregida.');
                }
            }
            $food = isset($data['food_item_id']) ? FoodItem::findOrFail($data['food_item_id']) : null;
            if ($food && $data['unit'] !== $food->reference_unit) {
                throw ValidationException::withMessages(['unit' => 'Usa la unidad de referencia del alimento.']);
            }
            $snapshot = ['name' => $food?->name ?? $data['name'], 'source' => $food?->source ?? 'unknown', 'confidence' => $food?->confidence, 'estimated_at' => now(), 'supersedes_id' => $itemId, 'grams' => $data['unit'] === 'g' ? $data['quantity'] : null];
            $snapshot += app(NutritionTotals::class)->portion($food, $data['quantity']);

            return $meal->mealEntryItems()->save((new MealEntryItem)->forceFill($snapshot + $data));
        });
    }
}
