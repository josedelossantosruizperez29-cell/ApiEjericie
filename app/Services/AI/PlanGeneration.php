<?php

namespace App\Services\AI;

use App\Models\AiGeneration;
use App\Models\User;
use App\Services\ExerciseCatalog;
use App\Services\GeneratedPlanImporter;
use App\Services\TrainingDifficulty;
use App\Services\TrainingMode;
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
            $mode = TrainingMode::forUser($user);
            if (! $mode) {
                throw ValidationException::withMessages(['training_mode' => 'Elige cómo quieres entrenar antes de generar un plan.']);
            }
            if ($user->aiGenerations()->where('status', 'processing')->where('requested_at', '>', now()->subMinutes(3))->exists()) {
                throw new \DomainException('Generación en curso.');
            }
            $today = app(TrainingReports::class)->today($user)->toDateString();
            if ($user->trainingPlans()->where('status', 'active')->where('ends_on', '>=', $today)->exists()) {
                throw new \DomainException('Ya existe un plan activo o futuro.');
            }

            return $user->aiGenerations()->save((new AiGeneration)->forceFill(['kind' => 'training_plan', 'model' => config('gemini.model') ?: 'unconfigured', 'schema_version' => '1', 'training_difficulty' => $user->userPreference()->value('training_difficulty') ?? 'intermediate', 'training_mode' => $mode, 'prompt_version' => 'fit-plan-v4', 'requested_at' => now(), 'request_key' => $key, 'status' => 'processing']));
        });
        if ($generation->wasRecentlyCreated) {
            try {
                $goal = $user->userGoals()->where('status', 'active')->orderBy('priority')->firstOrFail();
                $day = app(TrainingReports::class)->today($user);
                $context = ['today' => $day->toDateString(), 'last_day' => $day->addDays(6)->toDateString(), 'goal' => $goal->only(['id', 'kind']), 'level' => $user->userProfile->level, 'available_weekdays_iso' => $user->userOnboarding->weekly_availability, 'minutes' => $user->userOnboarding->session_minutes,
                    'training_difficulty' => $generation->training_difficulty,
                    'training_mode' => $generation->training_mode,
                    'limits' => TrainingDifficulty::limits($user, $generation->training_difficulty),
                    'recovery' => (bool) $user->userPreference?->recovery,
                    'equipment' => $user->availableEquipment()->pluck('slug'),
                    'recent_sessions' => $user->workoutSessions()->latest('scheduled_date')->limit(7)->get(['status', 'kind']),
                    'avoided_movements' => $user->userHealthConstraints->pluck('avoided_movements')->flatten()->filter()->values(),
                    'exercises' => $this->catalog->eligible($user, $generation->training_difficulty, $generation->training_mode)->get(['id', 'name', 'difficulty', 'movement', 'measurement', 'rep_limits'])->toArray()];
                [$text,$usage] = $this->client->generate('Organiza una semana entre today y last_day usando solamente el objetivo, ejercicios, disponibilidad y límites suministrados. Usa reps para repeticiones o seconds para tiempo. estimated_seconds es por serie. Con easy elige variantes sencillas, menos ejercicios y series, descanso amplio y progresión gradual. Con intermediate equilibra volumen y recuperación. Con hard progresa en variantes, series o duración según experiencia y recuperación, sin acortar automáticamente los descansos. No prescribas cargas. Respeta training_mode: con machine prioriza las estaciones de la EVO 500 y añade complementos solo si son compatibles con el equipamiento disponible; con bodyweight usa exclusivamente ejercicios que no necesiten máquina, pesas, mancuernas, banco ni accesorio. Nunca inventes ejercicios ni uses ids fuera de la lista exercises.', $context, json_decode(file_get_contents(resource_path('schemas/training-plan.json')), true, flags: JSON_THROW_ON_ERROR));
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
                if (in_array($reason, ['timeout', 'connection', 'dns', 'provider_quota', 'provider_error'], true)) {
                    try {
                        $this->recoverPriorPlan($user, $generation);
                    } catch (ValidationException) {
                        // El historial ya no es apto. Conservar el error original del proveedor.
                    }
                }
                $user->aiGenerations()->whereKey($generation->id)->where('status', 'processing')->update(['status' => 'failed', 'error_code' => $reason]);
            }
        }

        return $generation->refresh()->load('aiPlanRecommendations.trainingPlan.workoutSessions');
    }

    private function recoverPriorPlan(User $user, AiGeneration $generation): void
    {
        DB::transaction(function () use ($user, $generation) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked = $user->aiGenerations()->lockForUpdate()->findOrFail($generation->id);
            if ($locked->status !== 'processing') {
                return;
            }
            $today = app(TrainingReports::class)->today($user);
            $goal = $user->userGoals()->where('status', 'active')->orderBy('priority')->first();
            $plan = $user->trainingPlans()->where('goal_id', $goal?->id)
                ->where('training_difficulty', $locked->training_difficulty)
                ->where(fn ($q) => $locked->training_mode === null
                    ? $q->whereNull('training_mode')
                    : $q->where('training_mode', $locked->training_mode))
                ->where('ends_on', '<', $today->toDateString())
                ->whereHas('aiPlanRecommendations', fn ($q) => $q->where('validation_status', 'accepted'))
                ->latest('ends_on')->first();
            if (! $plan) {
                return;
            }
            $days = [];
            foreach ($plan->workoutSessions()->where('status', 'completed')->with('workoutExercises')->orderBy('scheduled_date')->get() as $session) {
                $date = $today->addDays(($session->scheduled_date->dayOfWeekIso - $today->dayOfWeekIso + 7) % 7)->toDateString();
                $items = [];
                foreach ($session->workoutExercises->sortBy('position') as $item) {
                    $items[] = array_intersect_key($item->prescription, array_flip(['id', 'sets', 'reps', 'seconds', 'rest_seconds', 'estimated_seconds']));
                }
                if ($items) {
                    $days[$date] = ['date' => $date, 'exercises' => $items];
                }
            }
            if (! $days) {
                return;
            }
            ksort($days);
            $payload = ['goal_id' => $plan->goal_id, 'starts_on' => array_key_first($days), 'ends_on' => array_key_last($days), 'sessions' => array_values($days)];
            $result = $this->importer->import($user, $locked->id, json_encode($payload, JSON_THROW_ON_ERROR));
            if ($result->validation_status !== 'accepted') {
                throw ValidationException::withMessages(['history' => 'El historial ya no es compatible.']);
            }
            $result->trainingPlan->forceFill(['source' => 'recovered'])->save();
            $result->forceFill(['reason' => 'revalidated_history', 'recommendation' => $result->recommendation + ['previous_plan_id' => $plan->id]])->save();
        });
    }
}
