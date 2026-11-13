<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'last_name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function userEquipment(): HasMany
    {
        return $this->hasMany(UserEquipment::class, 'user_id');
    }

    public function guestAccessTokens(): HasMany
    {
        return $this->hasMany(GuestAccessToken::class, 'user_id');
    }

    public function userProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    public function userGoals(): HasMany
    {
        return $this->hasMany(UserGoal::class, 'user_id');
    }

    public function userOnboarding(): HasOne
    {
        return $this->hasOne(UserOnboarding::class, 'user_id');
    }

    public function userHealthConstraints(): HasMany
    {
        return $this->hasMany(UserHealthConstraint::class, 'user_id');
    }

    public function userPreference(): HasOne
    {
        return $this->hasOne(UserPreference::class, 'user_id');
    }

    public function trainingPlans(): HasMany
    {
        return $this->hasMany(TrainingPlan::class, 'user_id');
    }

    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class, 'user_id');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class, 'user_id');
    }

    public function mealEntries(): HasMany
    {
        return $this->hasMany(MealEntry::class, 'user_id');
    }

    public function nutritionGoals(): HasMany
    {
        return $this->hasMany(NutritionGoal::class, 'user_id');
    }

    public function nutritionAssessments(): HasMany
    {
        return $this->hasMany(NutritionAssessment::class, 'user_id');
    }

    public function weightLogs(): HasMany
    {
        return $this->hasMany(WeightLog::class, 'user_id');
    }

    public function bodyMeasurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class, 'user_id');
    }

    public function streakEvents(): HasMany
    {
        return $this->hasMany(StreakEvent::class, 'user_id');
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class, 'user_id');
    }

    public function notificationEvents(): HasMany
    {
        return $this->hasMany(NotificationEvent::class, 'user_id');
    }

    public function aiGenerations(): HasMany
    {
        return $this->hasMany(AiGeneration::class, 'user_id');
    }

    public function aiPlanRecommendations(): HasMany
    {
        return $this->hasMany(AiPlanRecommendation::class, 'user_id');
    }

    public function availableEquipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'user_equipment');
    }
}
