<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CatalogRequest;
use App\Http\Resources\ApiResult;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Services\ExerciseCatalog;

class CatalogController
{
    public function equipment()
    {
        return new ApiResult(Equipment::with('equipmentCapabilities')->where('active', true)->orderBy('slug')->paginate(50));
    }

    public function exercises(CatalogRequest $r, ExerciseCatalog $catalog)
    {
        $query = $r->boolean('eligible') || $r->routeIs('eligible') ? $catalog->eligible($r->user()) : Exercise::where('active', true);
        foreach (['level' => 'difficulty', 'category' => 'category', 'measurement' => 'measurement'] as $param => $column) {
            if ($r->filled($param)) {
                $query->where($column, $r->validated($param));
            }
        }
        if ($r->filled('equipment_id')) {
            $query->whereHas('requiredEquipment', fn ($q) => $q->whereKey($r->validated('equipment_id')));
        }
        if ($r->filled('muscle_group')) {
            $query->whereHas('muscleGroups', fn ($q) => $q->where('slug', $r->validated('muscle_group')));
        }

        return new ApiResult($query->with('requiredEquipment', 'muscleGroups')->orderBy('slug')->paginate(50));
    }

    public function exercise(string $id)
    {
        return new ApiResult(Exercise::with('requiredEquipment', 'muscleGroups', 'lowerImpact')->where('active', true)->findOrFail($id));
    }
}
