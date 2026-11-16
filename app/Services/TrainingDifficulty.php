<?php

namespace App\Services;

use App\Models\User;

class TrainingDifficulty
{
    public static function limits(User $user, string $difficulty): array
    {
        $level = config('training.levels.'.($user->userProfile?->level ?? 'beginner'));
        $configured = $user->userPreference?->settings['plan_limits'] ?? [];
        $policy = config("training.difficulties.$difficulty");
        if ($user->userPreference?->recovery) {
            $recovery = config('training.difficulties.easy');
            foreach (['max_sets', 'max_reps', 'max_seconds_per_set', 'max_exercises'] as $key) {
                $policy[$key] = min($policy[$key], $recovery[$key]);
            }
            $policy['min_rest_seconds'] = max($policy['min_rest_seconds'], $recovery['min_rest_seconds']);
        }
        $limits = $policy + $level + config('training');
        foreach (['max_sets', 'max_reps', 'max_seconds_per_set', 'max_exercises', 'max_rest_seconds'] as $key) {
            $limits[$key] = min($limits[$key], $level[$key] ?? PHP_INT_MAX, $configured[$key] ?? PHP_INT_MAX);
        }
        $limits['min_rest_seconds'] = max($limits['min_rest_seconds'], $configured['min_rest_seconds'] ?? 0);
        $limits['seconds_per_rep'] = max(config('training.seconds_per_rep'), $configured['seconds_per_rep'] ?? 0);

        return array_diff_key($limits, array_flip(['difficulties', 'levels']));
    }
}
