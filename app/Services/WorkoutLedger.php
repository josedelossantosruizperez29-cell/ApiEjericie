<?php

namespace App\Services;

use App\Models\NotificationEvent;
use App\Models\Penalty;
use App\Models\PenaltyRule;
use App\Models\StreakEvent;
use App\Models\User;
use App\Models\WorkoutPenaltyApplication;
use App\Models\WorkoutSession;
use DomainException;
use Illuminate\Support\Facades\DB;

class WorkoutLedger
{
    /** Called by the scheduler and on guest return. All writers lock the user before sessions. */
    public function reconcile(User $user): void
    {
        DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->expireDue($user);
        }, 3);
    }

    public function start(User $user, string $sessionId): WorkoutSession
    {
        return DB::transaction(function () use ($user, $sessionId) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->expireDue($user);
            $session = $user->workoutSessions()->lockForUpdate()->findOrFail($sessionId);
            if ($session->status === 'in_progress') {
                return $session;
            }
            $this->assertPlayable($session);
            $this->snapshotAdjustments($user->fresh(), $session);
            $session->forceFill(['status' => 'in_progress', 'started_at' => now()])->save();

            return $session;
        }, 3);
    }

    public function complete(User $user, string $sessionId): WorkoutSession
    {
        return DB::transaction(function () use ($user, $sessionId) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->expireDue($user);
            $session = $user->workoutSessions()->lockForUpdate()->findOrFail($sessionId);
            if ($session->status === 'completed') {
                return $session;
            }
            $this->assertPlayable($session);
            if ($session->status === 'scheduled') {
                $this->snapshotAdjustments($user->fresh(), $session);
            }
            $session->forceFill(['status' => 'completed', 'finished_at' => now()])->save();
            $oldest = $user->penalties()->where('status', 'pending')
                ->whereDate('missed_on', '<', $session->scheduled_date)
                ->orderBy('missed_on')->orderBy('activated_at')->orderBy('id')->lockForUpdate()->first();
            if ($oldest) {
                $oldest->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolved_session_id' => $session->id])->save();
                $user->notificationEvents()->save((new NotificationEvent)->forceFill([
                    'event_key' => 'resolved:'.$oldest->id, 'kind' => 'penalty_resolved', 'scheduled_at' => now(),
                ]));
            }
            $this->recordEvent($session, 'completed');

            return $session;
        }, 3);
    }

    private function assertPlayable(WorkoutSession $session): void
    {
        if ($session->kind === 'rest' || ! in_array($session->status, ['scheduled', 'in_progress'], true)
            || $session->scheduled_date->toDateString() > now($session->timezone)->toDateString()
            || $session->cutoff_at->isPast()) {
            throw new DomainException('La jornada no está disponible para entrenamiento.');
        }
    }

    private function expireDue(User $user): void
    {
        $due = $user->workoutSessions()->whereIn('status', ['scheduled', 'in_progress'])
            ->where('kind', '<>', 'rest')->where('cutoff_at', '<=', now())->orderBy('cutoff_at')->lockForUpdate()->get();
        foreach ($due as $session) {
            $session->status = 'missed';
            $session->save();
            $rule = PenaltyRule::where('slug', 'daily-missed-v1')->sole();
            if ($rule->active) {
                $user->penalties()->save((new Penalty)->forceFill([
                    'origin_session_id' => $session->id, 'rule_id' => $rule->id,
                    'missed_on' => $session->scheduled_date, 'activated_at' => $session->cutoff_at,
                ]));
            }
            $this->recordEvent($session, 'missed');
        }
    }

    private function recordEvent(WorkoutSession $session, string $kind): void
    {
        $session->streakEvents()->save((new StreakEvent)->forceFill([
            'user_id' => $session->user_id, 'event_key' => $session->id.':'.$kind,
            'kind' => $kind, 'local_date' => $session->scheduled_date, 'occurred_at' => now(),
        ]));
    }

    private function snapshotAdjustments(User $user, WorkoutSession $session): void
    {
        $pending = $user->penalties()->where('status', 'pending')->count();
        if (! $pending) {
            return;
        }
        $rule = PenaltyRule::where('slug', 'daily-missed-v1')->sole();
        $preferences = $user->userPreference;
        $level = $user->userProfile?->level;
        $restricted = $user->userHealthConstraints()->exists() || $preferences?->recovery;
        foreach ($session->workoutExercises()->with('exercise')->get() as $item) {
            $exercise = $item->exercise;
            $original = array_merge($item->prescription, ['base_reps' => $item->base_reps, 'sets' => $item->sets,
                'load_kg' => $item->load_kg, 'duration_seconds' => $item->duration_seconds, 'distance_m' => $item->distance_m, 'rest_seconds' => $item->rest_seconds]);
            $applied = $original;
            $reason = 'disabled';
            if ($rule->active && $rule->adjustments_enabled && $preferences?->penalty_adjustments) {
                $reason = 'ineligible';
                if (! $restricted && $exercise->active && $exercise->measurement === 'reps' && $exercise->penalty_eligible && $item->base_reps) {
                    $limit = $exercise->rep_limits[$level] ?? 0;
                    $requested = $pending * $rule->increment_reps;
                    $extra = min($requested, $rule->max_extra_reps, $preferences->max_extra_reps,
                        is_int($limit) ? max(0, $limit - $item->base_reps) : 0);
                    $applied['base_reps'] += $extra;
                    $reason = $extra < $requested ? 'capped' : 'applied';
                }
            }
            $item->workoutPenaltyApplication()->save((new WorkoutPenaltyApplication)->forceFill([
                'rule_id' => $rule->id, 'pending_count' => $pending, 'original' => $original,
                'applied' => $applied, 'reason' => $reason,
            ]));
        }
    }
}
