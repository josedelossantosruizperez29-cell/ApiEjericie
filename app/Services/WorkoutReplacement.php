<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use DomainException;
use Illuminate\Support\Facades\DB;

class WorkoutReplacement
{
    public function replace(User $user, string $sessionId, string $assignmentId, string $previousId, string $operation): array
    {
        return DB::transaction(function () use ($user, $sessionId, $assignmentId, $previousId, $operation) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $session = $user->workoutSessions()->lockForUpdate()->findOrFail($sessionId);
            $audit = DB::table('workout_replacements')->where('session_id', $sessionId);
            $prior = (clone $audit)->where('operation_id', $operation)->first();
            if ($prior) {
                if ($prior->assignment_id !== $assignmentId || $prior->previous_id !== $previousId) {
                    throw new DomainException('La operación pertenece a otro cambio.');
                }

                return json_decode($prior->result, true, flags: JSON_THROW_ON_ERROR);
            }
            $item = $session->workoutExercises()->lockForUpdate()->findOrFail($assignmentId);
            if ($session->status !== 'in_progress' || $session->cutoff_at->isPast()) {
                throw new DomainException('La sesión debe estar en curso.');
            }
            if ($session->replacements_used >= 3) {
                throw new DomainException('Has utilizado los tres reemplazos.');
            }
            if ($item->exercise_id !== $previousId || $item->exerciseSetLogs()->exists()) {
                throw new DomainException('El ejercicio ha cambiado o ya tiene series registradas.');
            }
            $next = $this->alternative($user, $session, $item, $audit->pluck('previous_id')->all());
            $prescription = array_merge($item->prescription, ['id' => $next->id, 'name' => $next->name, 'measurement' => $next->measurement]);
            $item->forceFill(['exercise_id' => $next->id, 'prescription' => $prescription])->save();
            $session->increment('replacements_used');
            $result = [
                'session_id' => $sessionId, 'previous_exercise_id' => $previousId,
                'assignment' => $item->load('exercise.muscleGroups', 'exerciseSetLogs', 'workoutPenaltyApplication')->toArray(),
                'replacements_used' => $session->replacements_used, 'replacements_remaining' => 3 - $session->replacements_used,
            ];
            DB::table('workout_replacements')->insert([
                'session_id' => $sessionId, 'operation_id' => $operation, 'assignment_id' => $assignmentId,
                'previous_id' => $previousId, 'new_id' => $next->id, 'result' => json_encode($result, JSON_THROW_ON_ERROR),
            ]);

            return $result;
        }, 3);
    }

    private function alternative(User $user, WorkoutSession $session, WorkoutExercise $item, array $rejected): Exercise
    {
        $original = $item->exercise;
        $difficulty = $session->trainingPlan->training_difficulty;
        $limits = TrainingDifficulty::limits($user, $difficulty);
        $applied = $item->workoutPenaltyApplication?->applied['base_reps'] ?? $item->base_reps;
        $primary = $original->exerciseMuscleGroups()->where('role', 'primary')->pluck('muscle_group_id');
        $candidates = app(ExerciseCatalog::class)->eligible($user, $difficulty)
            ->where('measurement', $original->measurement)
            ->whereNotIn('id', $session->workoutExercises()->select('exercise_id'))
            ->where(function ($q) use ($original, $primary) {
                $q->whereHas('exerciseMuscleGroups', fn ($g) => $g->whereIn('muscle_group_id', $primary))
                    ->orWhere('movement', $original->movement);
                if ($original->category === 'cardio') {
                    $q->orWhere('category', 'cardio');
                }
            })->with('exerciseMuscleGroups')->get();
        $ranks = ['beginner' => 0, 'intermediate' => 1, 'advanced' => 2];
        $best = $candidates->filter(function ($e) use ($item, $original, $user, $applied, $ranks, $limits) {
            $cap = $e->rep_limits[$user->userProfile->level] ?? $limits['max_reps'];

            return $ranks[$e->difficulty] <= $ranks[$original->difficulty]
                && (! $applied || $applied <= $cap)
                && ($applied <= $item->base_reps || $e->penalty_eligible)
                && (! $item->load_kg || $original->movement === $e->movement);
        })->sortBy(fn ($e) => [
            in_array($e->id, $rejected, true),
            ! $e->exerciseMuscleGroups->where('role', 'primary')->whereIn('muscle_group_id', $primary)->count(),
            $e->movement !== $original->movement, $e->difficulty !== $original->difficulty, $e->slug,
        ])->first();

        return $best ?? throw new DomainException('No hay otra alternativa compatible con esta prescripción.');
    }
}
