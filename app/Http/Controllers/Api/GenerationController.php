<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\GenerationRequest;
use App\Http\Resources\ApiResult;
use App\Services\AI\PlanGeneration;
use Illuminate\Http\Request;

class GenerationController
{
    public function generate(GenerationRequest $request, PlanGeneration $plans)
    {
        $generation = $plans->generate($request->user(), $request->validated('request_key'));
        if (in_array($generation->status, ['failed', 'invalid'], true)) {
            $status = $generation->status === 'invalid' ? 422 : (in_array($generation->error_code, ['invalid_response', 'incomplete_response'], true) ? 502 : 503);

            return response()->json(['success' => false, 'message' => 'No se pudo generar el plan.', 'errors' => ['generation_id' => $generation->id, 'reason' => $generation->error_code]], $status);
        }

        return (new ApiResult($generation))->response()->setStatusCode($generation->status === 'processing' ? 202 : 200);
    }

    public function show(Request $r, string $id)
    {
        return new ApiResult($r->user()->aiGenerations()->with('aiPlanRecommendations.trainingPlan')->findOrFail($id));
    }
}
