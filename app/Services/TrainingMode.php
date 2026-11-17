<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Models\WorkoutExercise;
use Illuminate\Database\Eloquent\Builder;

/** Source of truth for the training modality: machine (EVO 500) or bodyweight at home. */
class TrainingMode
{
    public const MACHINE = 'machine';

    public const BODYWEIGHT = 'bodyweight';

    public const VALUES = [self::MACHINE, self::BODYWEIGHT];

    public static function isValid(?string $mode): bool
    {
        return $mode === null || in_array($mode, self::VALUES, true);
    }

    public static function forUser(User $user): ?string
    {
        $mode = $user->userPreference?->training_mode;

        return in_array($mode, self::VALUES, true) ? $mode : null;
    }

    /** Bodyweight training can only use exercises that need no equipment at all. */
    public static function scope(Builder $query, ?string $mode): Builder
    {
        if ($mode === self::BODYWEIGHT) {
            $query->where('category', '<>', self::MACHINE)->whereDoesntHave('requiredEquipment');
        }

        return $query;
    }

    /** A plan keeps the modality it was generated with; legacy plans derive it from their exercises. */
    public static function forPlan(TrainingPlan $plan): ?string
    {
        $mode = $plan->training_mode;

        if (in_array($mode, self::VALUES, true)) {
            return $mode;
        }

        $hasMachine = WorkoutExercise::query()
            ->whereIn('workout_session_id', $plan->workoutSessions()->select('id'))
            ->whereHas('exercise', fn ($q) => $q->where('category', self::MACHINE))
            ->exists();

        return $hasMachine ? self::MACHINE : self::BODYWEIGHT;
    }

    /** Choosing the machine modality implies the machine: attach it only when the profile has no equipment yet. */
    public static function attachMachine(User $user): void
    {
        if ($user->availableEquipment()->exists()) {
            return;
        }

        $machine = Equipment::query()->where('active', true)->where('category', 'multigym')->orderBy('slug')->first();
        if ($machine) {
            $user->availableEquipment()->syncWithoutDetaching([$machine->id]);
        }
    }
}
