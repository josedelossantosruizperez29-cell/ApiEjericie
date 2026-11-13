<?php

namespace App\Services;

use App\Models\ExerciseSetLog;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutResults
{
    private const METRICS = ['reps' => ['reps', 1000], 'time' => ['duration_seconds', 86400], 'distance' => ['distance_m', 100000]];

    public function record(User $user, string $sessionId, array $data, ?string $setId = null): ExerciseSetLog
    {
        return DB::transaction(function () use ($user, $sessionId, $data, $setId) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $session = $user->workoutSessions()->lockForUpdate()->findOrFail($sessionId);
            if ($session->status !== 'in_progress' || $session->cutoff_at->isPast()) {
                throw new DomainException('Jornada cerrada o sin iniciar.');
            }
            $item = $session->workoutExercises()->findOrFail($data['workout_exercise_id']);
            if (! $this->valid($item, $data)) {
                throw ValidationException::withMessages(['set' => ['Serie incompatible con la prescripción.']]);
            }
            $log = $setId ? $item->exerciseSetLogs()->findOrFail($setId) : $item->exerciseSetLogs()->where('set_number', $data['set_number'])->first();
            unset($data['workout_exercise_id']);
            if ($log && $log->set_number != $data['set_number']) {
                throw new DomainException('Número de serie incompatible.');
            }
            if ($log && ! $setId) {
                foreach ($data as $key => $value) {
                    if (($value === null) !== ($log->$key === null) || $value != $log->$key) {
                        throw new DomainException('La serie ya existe con otro resultado.');
                    }
                }

                return $log;
            }
            $log ??= new ExerciseSetLog;
            $log->forceFill($data + ['logged_at' => now()]);

            return $item->exerciseSetLogs()->save($log);
        });
    }

    public function progress(WorkoutSession $session): array
    {
        $session->loadMissing('workoutExercises.exercise', 'workoutExercises.exerciseSetLogs', 'workoutExercises.workoutPenaltyApplication');
        $done = 0;
        $total = 0;
        foreach ($session->workoutExercises as $item) {
            $total += $item->sets;
            $mode = $item->prescription['measurement'] ?? $item->exercise->measurement;
            $metric = self::METRICS[$mode][0];
            $goal = match ($mode) {
                'reps' => $item->workoutPenaltyApplication?->applied['base_reps'] ?? $item->base_reps,'time' => $item->duration_seconds,'distance' => $item->distance_m
            };
            foreach ($item->exerciseSetLogs as $log) {
                if ($this->valid($item, $log->getAttributes()) && $goal > 0 && $goal <= $log->$metric) {
                    $done++;
                }
            }
        }

        return ['completed_sets' => $done, 'planned_sets' => $total, 'required_percent' => (int) $session->completion_percent, 'fulfilled' => $total > 0 && 100 * $done >= $total * $session->completion_percent];
    }

    private function valid(WorkoutExercise $item, array $data): bool
    {
        [$field,$max] = self::METRICS[$item->prescription['measurement'] ?? $item->exercise->measurement];
        foreach (self::METRICS as [$key]) {
            if ($key !== $field && isset($data[$key])) {
                return false;
            }
        }

        return ($data['set_number'] ?? 0) >= 1 && $data['set_number'] <= $item->sets && ($data[$field] ?? 0) > 0 && $data[$field] <= $max
            && ($data['load_kg'] ?? 0) >= 0 && ($data['load_kg'] ?? 0) <= 1000 && ($data['perceived_effort'] ?? 1) >= 1 && ($data['perceived_effort'] ?? 1) <= 10;
    }
}
