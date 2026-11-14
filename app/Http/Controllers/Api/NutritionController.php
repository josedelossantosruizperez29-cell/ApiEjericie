<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AnalyzeMealRequest;
use App\Http\Requests\MealRequest;
use App\Http\Requests\PortionRequest;
use App\Http\Resources\ApiResult;
use App\Models\FoodItem;
use App\Services\AI\NutritionAssistant;
use App\Services\MealJournal;
use App\Services\NutritionTotals;
use App\Services\TrainingReports;
use Illuminate\Http\Request;

class NutritionController
{
    public function analyze(AnalyzeMealRequest $request, NutritionAssistant $assistant)
    {
        return new ApiResult($assistant->analyze($request->validated('description'), $request->validated('meal_type')));
    }

    public function foods()
    {
        return new ApiResult(FoodItem::orderBy('name')->paginate(50));
    }

    public function meals(Request $r)
    {
        return new ApiResult($r->user()->mealEntries()->with('mealEntryItems')->latest('eaten_at')->paginate(30));
    }

    public function meal(Request $r, string $id, NutritionTotals $totals)
    {
        return new ApiResult(['meal' => $r->user()->mealEntries()->with('mealEntryItems')->findOrFail($id), 'totals' => $totals->forMeal($r->user(), $id)]);
    }

    public function create(MealRequest $r, MealJournal $journal)
    {
        return (new ApiResult($journal->create($r->user(), $r->validated())))->response()->setStatusCode(201);
    }

    public function portion(PortionRequest $r, string $id, MealJournal $journal, ?string $itemId = null)
    {
        return (new ApiResult($journal->portion($r->user(), $id, $r->validated(), $itemId)))->response()->setStatusCode(201);
    }

    public function today(Request $r, NutritionTotals $totals, TrainingReports $reports)
    {
        return new ApiResult($totals->forDay($r->user(), $reports->today($r->user())->toDateString()));
    }

    public function history(Request $r, NutritionTotals $totals)
    {
        return new ApiResult($totals->history($r->user()));
    }
}
