<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ExerciseCatalog
{
    /** Candidate set for a future plan validator. Unknown free-text restrictions require human review. */
    public function eligible(User $user, string $difficulty = 'intermediate', ?string $mode = null): Builder
    {
        $profile = $user->userProfile;
        $levels = match ($profile?->level) {
            'advanced' => ['beginner', 'intermediate', 'advanced'],
            'intermediate' => ['beginner', 'intermediate'],
            'beginner' => ['beginner'],
            default => [],
        };
        $query = Exercise::query()->where('active', true)->whereIn('difficulty', $levels)
            ->whereDoesntHave('requiredEquipment', fn ($q) => $q->where('active', false)
                ->orWhereDoesntHave('owners', fn ($owners) => $owners->whereKey($user->id)));
        if ($difficulty === 'easy' || $user->userPreference?->recovery) {
            $query->where('difficulty', 'beginner');
        }
        foreach ($user->userHealthConstraints as $constraint) {
            if (! $constraint->exercise_id && ! $constraint->avoided_movements) {
                return $query->whereRaw('false');
            }
            if ($constraint->exercise_id) {
                $query->where('id', '<>', $constraint->exercise_id);
            }
            $query->whereNotIn('movement', $constraint->avoided_movements ?? []);
        }

        return TrainingMode::scope($query, $mode);
    }
}
