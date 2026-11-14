<?php

namespace App\Services\AI;

use App\Models\AiGeneration;
use App\Models\User;
use App\Services\ExerciseCatalog;
use App\Services\GeneratedPlanImporter;
use App\Services\TrainingReports;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlanGeneration
{
    public function __construct(private GeminiClient $client, private ExerciseCatalog $catalog, private GeneratedPlanImporter $importer) {}

    public function generate(User $user, string $key): AiGeneration
    {
        $generation = DB::transaction(function () use ($user, $key) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->aiGenerations()->where('status', 'processing')->where('requested_at', '<=', now()->subMinutes(3))->update(['status' => 'failed', 'error_code' => 'interrupted']);
            $previous = $user->aiGenerations()->where('request_key', $key)->first();
            if ($previous) {
                return $previous;
            }
            if (! $user->userOnboarding?->completed_at) {
                throw ValidationException::withMessages(['onboarding' => 'Completa el cuestionario.']);
            }
            if ($user->aiGenerations()->where('status', 'processing')->where('requested_at', '>', now()->subMinutes(3))->exists()) {
                throw new \DomainException('Generación en curso.');
            }
            $today = app(TrainingReports::class)->today($user)->toDateString();
            if ($user->trainingPlans()->where('status', 'active')->where('ends_on', '>=', $today)->exists()) {
                throw new \DomainException('Ya existe un plan activo o futuro.');
            }

            return $user->aiGenerations()->save((new AiGeneration)->forceFill(['kind' => 'training_plan', 'model' => config('gemini.model') ?: 'unconfigured', 'schema_version' => '1', 'prompt_version' => 'fit-plan-v2', 'requested_at' => now(), 'request_key' => $key, 'status' => 'processing']));
        });
        if ($generation->wasRecentlyCreated) {
            try {
                $goal = $user->userGoals()->where('status', 'active')->orderBy('priority')->firstOrFail();
                $day = app(TrainingReports::class)->today($user);
                $context = ['today' => $day->toDateString(), 'last_day' => $day->addDays(6)->toDateString(), 'goal' => $goal->only(['id', 'kind']), 'level' => $user->userProfile->level, 'available_weekdays_iso' => $user->userOnboarding->weekly_availability, 'minutes' => $user->userOnboarding->session_minutes,
                    'limits' => ($user->userPreference?->settings['plan_limits'] ?? []) + config('training.levels.'.$user->userProfile->level) + array_intersect_key(config('training'), array_flip(['min_rest_seconds', 'max_rest_seconds', 'seconds_per_rep'])),
                    'exercises' => $this->catalog->eligible($user)->get(['id', 'name', 'measurement', 'rep_limits'])->toArray()];
                [$text,$usage] = $this->client->generate('Organiza una semana entre today y last_day usando solamente el objetivo, ejercicios, disponibilidad y límites suministrados. Usa reps para repeticiones o seconds para tiempo. estimated_seconds es por serie. No prescribas cargas.', $context, json_decode(file_get_contents(resource_path('schemas/training-plan.json')), true, flags: JSON_THROW_ON_ERROR));
                DB::transaction(function () use ($generation, $user, $text, $usage) {
                    User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $locked = $user->aiGenerations()->lockForUpdate()->findOrFail($generation->id);
                    if ($locked->status !== 'processing') {
                        return;
                    }
                    $this->importer->import($user, $locked->id, $text);
                    $locked->usage = $usage;
                    $locked->save();
                });
            } catch (\Throwable $e) {
                $reason = match (true) {
                    $e instanceof HttpException && in_array($e->getMessage(), ['configuration', 'timeout', 'connection', 'dns', 'provider_quota', 'permission', 'authentication', 'model_unavailable', 'invalid_request', 'provider_error', 'invalid_response', 'incomplete_response'], true) => $e->getMessage(),
                    $e instanceof QueryException => 'database',
                    $e instanceof ModelNotFoundException => 'missing_context',
                    default => 'internal_error',
                };
                $user->aiGenerations()->whereKey($generation->id)->where('status', 'processing')->update(['status' => 'failed', 'error_code' => $reason]);
            }
        }

        return $generation->refresh()->load('aiPlanRecommendations.trainingPlan.workoutSessions');
    }
}
