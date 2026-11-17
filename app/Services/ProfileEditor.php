<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserGoal;
use App\Models\UserOnboarding;
use App\Models\UserPreference;
use App\Models\UserProfile;
use App\Models\WeightLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfileEditor
{
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->fill(Arr::only($data, ['name', 'last_name']))->save();
            $profile = $user->userProfile ?? new UserProfile;
            $profile->forceFill(Arr::only($data, ['weight_kg', 'height_cm', 'level', 'timezone', 'birth_date', 'age_years']));
            if (isset($data['age_years'])) {
                $profile->age_as_of = today();
                $profile->birth_date = null;
            }
            if (isset($data['birth_date'])) {
                $profile->age_as_of = null;
                $profile->age_years = null;
            }
            if (isset($data['weight_kg']) && $profile->isDirty('weight_kg')) {
                $user->weightLogs()->save((new WeightLog)->forceFill(['weight_kg' => $data['weight_kg'], 'measured_at' => now(), 'source' => 'profile']));
            }
            $user->userProfile()->save($profile);
            if (isset($data['equipment_ids'])) {
                $user->availableEquipment()->sync($data['equipment_ids']);
            }
            if (isset($data['goal']) && ! $user->userGoals()->where('status', 'active')->where('kind', $data['goal'])->exists()) {
                $user->userGoals()->where('status', 'active')->update(['status' => 'archived']);
                $user->userGoals()->save((new UserGoal)->forceFill(['kind' => $data['goal'], 'start_date' => today()]));
            }
            $onboarding = $user->userOnboarding ?? new UserOnboarding;
            $onboarding->forceFill(Arr::only($data, ['step', 'session_minutes', 'weekly_availability']));
            if ($data['complete'] ?? false) {
                if (! $profile->height_cm || ! $profile->weight_kg || (! $profile->birth_date && ! $profile->age_years) || ! $onboarding->session_minutes || ! $onboarding->weekly_availability || ! $user->userGoals()->where('status', 'active')->exists()) {
                    throw ValidationException::withMessages(['complete' => 'Faltan datos físicos, objetivo o disponibilidad.']);
                }
                $onboarding->completed_at ??= now();
            }
            $user->userOnboarding()->save($onboarding);
            if (isset($data['exercise_preferences'])) {
                $this->preferences($user, Arr::only($data, ['exercise_preferences']));
            }

            return $user->fresh(['userProfile', 'userOnboarding', 'availableEquipment', 'userGoals']);
        });
    }

    public function preferences(User $user, array $data): UserPreference
    {
        return DB::transaction(function () use ($user, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $prefs = $user->userPreference()->first() ?? new UserPreference;
            $prefs->forceFill(Arr::except($data, 'exercise_preferences'));
            if (isset($data['exercise_preferences'])) {
                $prefs->settings = array_merge($prefs->settings ?? [], Arr::only($data, 'exercise_preferences'));
            }
            $user->userPreference()->save($prefs)->refresh();
            if (($data['training_mode'] ?? null) === TrainingMode::MACHINE) {
                TrainingMode::attachMachine($user);
            }

            return $prefs;
        });
    }
}
