<?php

namespace App\Services;

use App\Models\TrainingPlan;
use App\Models\TrainingPlanRevision;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class WorkoutCalendar
{
    public function schedule(User $user, string $planId, string $date, string $kind): WorkoutSession
    {
        return DB::transaction(function () use ($user, $planId, $date, $kind) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            return $this->create($user, $user->trainingPlans()->findOrFail($planId), $date, $kind);
        });
    }

    public function change(User $user, string $sessionId, ?string $newDate, string $reason): WorkoutSession
    {
        return DB::transaction(function () use ($user, $sessionId, $newDate, $reason) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $old = $user->workoutSessions()->lockForUpdate()->findOrFail($sessionId);
            if ($old->status !== 'scheduled' || $old->cutoff_at->isPast() || trim($reason) === '') {
                throw new DomainException('El cambio requiere una jornada programada vigente y una justificación.');
            }
            $old->status = $newDate ? 'rescheduled' : 'cancelled';
            $old->save();
            $plan = $old->trainingPlan;
            $result = $old;
            if ($newDate) {
                $result = $this->create($user, $plan, $newDate, $old->kind);
                $result->rescheduledFrom()->associate($old)->save();
                foreach ($old->workoutExercises as $exercise) {
                    $result->workoutExercises()->save($exercise->replicate());
                }
            }
            $plan->trainingPlanRevisions()->save((new TrainingPlanRevision)->forceFill([
                'version' => 1 + $plan->trainingPlanRevisions()->max('version'), 'source' => 'user',
                'justification' => $reason, 'changes' => ['session_id' => $old->id, 'status' => $old->status, 'new_date' => $newDate],
            ]));

            return $result;
        });
    }

    private function create(User $user, TrainingPlan $plan, string $date, string $kind): WorkoutSession
    {
        $zone = $user->userProfile?->timezone ?? 'UTC';
        $cutoff = CarbonImmutable::parse($date.' '.($user->userPreference?->daily_cutoff ?? '23:59:00'), $zone);
        if ($date !== $cutoff->toDateString() || $date < $plan->starts_on->toDateString()
            || $date > $plan->ends_on->toDateString() || $cutoff->isPast()) {
            throw new DomainException('Fecha fuera del periodo disponible.');
        }

        return $plan->workoutSessions()->save((new WorkoutSession)->forceFill([
            'user_id' => $user->id, 'scheduled_date' => $date, 'timezone' => $zone,
            'cutoff_at' => $cutoff->utc(), 'kind' => $kind,
        ]));
    }
}
