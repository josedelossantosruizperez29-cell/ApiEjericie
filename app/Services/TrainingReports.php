<?php

namespace App\Services;

use App\Models\PenaltyRule;
use App\Models\User;
use App\Models\WorkoutPenaltyApplication;
use Carbon\CarbonImmutable;

class TrainingReports
{
    public const DETAILS = ['workoutExercises.exercise.muscleGroups', 'workoutExercises.exerciseSetLogs', 'workoutExercises.workoutPenaltyApplication'];

    public function today(User $u): CarbonImmutable
    {
        return CarbonImmutable::now($u->userProfile?->timezone ?? 'UTC')->startOfDay();
    }

    public function week(User $u)
    {
        $start = $this->today($u)->startOfWeek(1);

        return $u->workoutSessions()->whereBetween('scheduled_date', [$start->toDateString(), $start->addDays(6)->toDateString()])->orderBy('scheduled_date');
    }

    public function summary(User $u): array
    {
        $pending = $u->penalties()->where('status', 'pending')->count();
        $rule = PenaltyRule::where('slug', 'daily-missed-v1')->sole();

        return ['active' => $pending > 0, 'pending' => $pending, 'resolved' => $u->penalties()->where('status', 'resolved')->count(), 'nominal_extra_reps' => $pending * $rule->increment_reps, 'rule' => $rule, 'next_session' => $this->next($u),
            'applications' => WorkoutPenaltyApplication::whereHas('workoutExercise.workoutSession', fn ($q) => $q->where('user_id', $u->id))->latest()->paginate(30)];
    }

    public function next(User $u)
    {
        return $u->workoutSessions()->whereIn('status', ['scheduled', 'in_progress'])->where('kind', '<>', 'rest')->where('cutoff_at', '>', now())->orderBy('scheduled_date')->first();
    }

    public function adherence(User $u): array
    {
        $rows = $this->week($u)->whereNotIn('status', ['cancelled', 'rescheduled'])->where('kind', '<>', 'rest')->pluck('status');
        $done = $rows->filter(fn ($s) => $s === 'completed')->count();

        return ['completed' => $done, 'scheduled' => $rows->count(), 'percent' => $rows->count() ? round($done * 100 / $rows->count(), 1) : null];
    }

    public function streak(User $u): int
    {
        $count = 0;
        foreach ($u->workoutSessions()->whereDate('scheduled_date', '<=', $this->today($u))->whereIn('status', ['completed', 'missed'])->where('kind', '<>', 'rest')->orderByDesc('scheduled_date')->cursor() as $session) {
            if ($session->status === 'missed') {
                break;
            }
            $count++;
        }

        return $count;
    }

    public function dashboard(User $u): array
    {
        app(WorkoutLedger::class)->reconcile($u);
        $date = $this->today($u)->toDateString();

        return ['name' => $u->name, 'goal' => $u->userGoals()->where('status', 'active')->orderBy('priority')->first(), 'weight_kg' => $u->weightLogs()->latest('measured_at')->value('weight_kg'), 'next_session' => $this->next($u),
            'today' => $u->workoutSessions()->with(self::DETAILS)->where('scheduled_date', $date)->whereNotIn('status', ['cancelled', 'rescheduled'])->first(), 'weekly' => $this->adherence($u), 'streak' => $this->streak($u), 'pending_penalties' => $u->penalties()->where('status', 'pending')->count(), 'nutrition' => app(NutritionTotals::class)->forDay($u, $date), 'next_notification' => $u->notificationEvents()->where('status', 'scheduled')->where('scheduled_at','>=',now())->orderBy('scheduled_at')->first()];
    }
}
