<?php

namespace App\Services;

use App\Models\AiPlanRecommendation;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Models\WorkoutExercise;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

class GeneratedPlanImporter
{
    /** Validate a stored v1 generation against backend policy. Only persist an allowlisted payload. */
    public function import(User $user, string $generationId, #[\SensitiveParameter] string $json): AiPlanRecommendation
    {
        return DB::transaction(function () use ($user, $generationId, $json) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $generation = $user->aiGenerations()->lockForUpdate()->findOrFail($generationId);
            $existing = $generation->aiPlanRecommendations()->first();
            if ($existing) {
                return $existing;
            }
            $record = (new AiPlanRecommendation)->forceFill(['user_id' => $user->id, 'generation_id' => $generation->id]);
            try {
                $result = DB::transaction(function () use ($user, $generation, $json) {
                    if ($generation->kind !== 'training_plan' || $generation->schema_version !== '1') {
                        $this->invalid('schema_version');
                    }
                    $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
                    $payload = Validator::make(['plan' => $data], [
                        'plan' => 'required|array:goal_id,starts_on,ends_on,sessions', 'plan.goal_id' => 'required|uuid',
                        'plan.starts_on' => 'required|date_format:Y-m-d', 'plan.ends_on' => 'required|date_format:Y-m-d|after_or_equal:plan.starts_on',
                        'plan.sessions' => 'required|array|list|min:1|max:7',
                        'plan.sessions.*' => 'required|array:date,exercises', 'plan.sessions.*.date' => 'required|distinct|date_format:Y-m-d',
                        'plan.sessions.*.exercises' => 'required|array|list|min:1|max:30',
                        'plan.sessions.*.exercises.*' => 'required|array:id,sets,reps,seconds,rest_seconds,estimated_seconds',
                        'plan.sessions.*.exercises.*.id' => 'required|uuid',
                        'plan.sessions.*.exercises.*.sets' => 'required|integer|min:1|max:100',
                        'plan.sessions.*.exercises.*.reps' => 'sometimes|integer|min:1|max:1000',
                        'plan.sessions.*.exercises.*.seconds' => 'sometimes|integer|min:1|max:86400',
                        'plan.sessions.*.exercises.*.rest_seconds' => 'required|integer|min:0|max:86400',
                        'plan.sessions.*.exercises.*.estimated_seconds' => 'required|integer|min:1|max:86400',
                    ])->validate()['plan'];

                    return $this->persist($user, $payload);
                });
                $record->forceFill(['training_plan_id' => $result->id, 'recommendation' => ['plan_version' => $result->version],
                    'validation_status' => 'accepted', 'reason' => 'validated_v1']);
                $generation->status = 'validated';
            } catch (ValidationException|JsonException|\DomainException $e) {
                $reason = $e instanceof ValidationException ? array_key_first($e->errors()) : ($e instanceof JsonException ? 'invalid_json' : 'calendar');
                $record->forceFill(['recommendation' => [], 'validation_status' => 'rejected', 'reason' => $reason]);
                $generation->status = 'invalid';
                $generation->error_code = $reason;
            }

            $generation->save();
            $record->save();

            return $record;
        }, 3);
    }

    private function persist(User $user, array $payload): TrainingPlan
    {
        $goal = $user->userGoals()->where('status', 'active')->find($payload['goal_id']);
        $onboarding = $user->userOnboarding;
        if (! $goal || ! $onboarding?->session_minutes || ! $onboarding->weekly_availability) {
            $this->invalid('user_context');
        }
        $plan = $user->trainingPlans()->save((new TrainingPlan)->forceFill([
            'goal_id' => $goal->id, 'starts_on' => $payload['starts_on'], 'ends_on' => $payload['ends_on'], 'source' => 'ai',
            'version' => 1 + $user->trainingPlans()->where('starts_on', $payload['starts_on'])->max('version'),
        ]));
        $catalog = app(ExerciseCatalog::class)->eligible($user)->get()->keyBy('id');
        $limits = $user->userPreference?->settings['plan_limits'] ?? [];
        foreach ($payload['sessions'] as $day) {
            $date = CarbonImmutable::parse($day['date']);
            if (! in_array($date->dayOfWeekIso, $onboarding->weekly_availability, true)
                || $user->workoutSessions()->whereDate('scheduled_date', $day['date'])->whereNotIn('status', ['cancelled', 'rescheduled'])->exists()) {
                $this->invalid('calendar');
            }
            $session = app(WorkoutCalendar::class)->schedule($user, $plan->id, $day['date'], 'training');
            $duration = 0;
            foreach ($day['exercises'] as $i => $item) {
                $exercise = $catalog->get($item['id']);
                if (! $exercise || $item['sets'] > ($limits['max_sets'] ?? 0)) {
                    $this->invalid('exercise');
                }
                $seconds = $item['seconds'] ?? null;
                $reps = $item['reps'] ?? null;
                $repLimit = $exercise->rep_limits[$user->userProfile->level] ?? 0;
                if ($exercise->measurement === 'reps' && $reps && ! $seconds && $reps <= $repLimit) {
                    $work = max($item['estimated_seconds'], $reps * max(1, (int) ($limits['seconds_per_rep'] ?? 1)));
                } elseif ($exercise->measurement === 'time' && $seconds && ! $reps && $seconds <= ($limits['max_seconds_per_set'] ?? 0)) {
                    $work = max($item['estimated_seconds'], $seconds);
                } else {
                    $this->invalid('prescription');
                }
                $duration += $item['sets'] * ($work + $item['rest_seconds']);
                if ($duration > $onboarding->session_minutes * 60) {
                    $this->invalid('duration');
                }
                $session->workoutExercises()->save((new WorkoutExercise)->forceFill([
                    'exercise_id' => $exercise->id, 'position' => $i + 1, 'sets' => $item['sets'], 'base_reps' => $reps, 'duration_seconds' => $seconds,
                    'rest_seconds' => $item['rest_seconds'], 'prescription' => $item + ['name' => $exercise->name, 'measurement' => $exercise->measurement],
                ]));
            }
        }

        return $plan;
    }

    private function invalid(string $field): never
    {
        throw ValidationException::withMessages([$field => ['invalid']]);
    }
}
