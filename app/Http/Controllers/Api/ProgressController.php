<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ReminderRequest;
use App\Http\Requests\WeightRequest;
use App\Http\Resources\ApiResult;
use App\Models\WeightLog;
use App\Services\TrainingReports;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ProgressController
{
    public function penalties(Request $r)
    {
        return new ApiResult($r->user()->penalties()->when(! $r->routeIs('penalty-history'), fn ($q) => $q->where('status', 'pending'))->latest('missed_on')->paginate(30));
    }

    public function summary(Request $r, TrainingReports $reports)
    {
        return new ApiResult($reports->summary($r->user()));
    }

    public function dashboard(Request $r, TrainingReports $reports)
    {
        return new ApiResult($reports->dashboard($r->user()));
    }

    public function workouts(Request $r, TrainingReports $reports)
    {
        return new ApiResult($reports->adherence($r->user()));
    }

    public function streaks(Request $r, TrainingReports $reports)
    {
        return new ApiResult(['current' => $reports->streak($r->user()), 'events' => $r->user()->streakEvents()->latest('occurred_at')->paginate(30)]);
    }

    public function weights(Request $r)
    {
        return new ApiResult($r->user()->weightLogs()->latest('measured_at')->paginate(30));
    }

    public function weight(WeightRequest $r)
    {
        $log = (new WeightLog)->forceFill(['source' => 'manual', 'weight_kg' => $r->validated('weight_kg'), 'measured_at' => CarbonImmutable::parse($r->validated('measured_at'))->utc()]);

        return (new ApiResult($r->user()->weightLogs()->save($log)))->response()->setStatusCode(201);
    }

    public function weightStats(Request $r)
    {
        $q = $r->user()->weightLogs();
        $first = (clone $q)->oldest('measured_at')->first();
        $last = $q->latest('measured_at')->first();

        return new ApiResult(['first' => $first, 'latest' => $last, 'change_kg' => $last ? round($last->weight_kg - $first->weight_kg, 3) : null]);
    }

    public function reminders(Request $r)
    {
        return new ApiResult($r->user()->notificationPreferences()->orderBy('kind')->get());
    }

    public function reminder(ReminderRequest $r)
    {
        $data = $r->validated();
        $r->user()->notificationPreferences()->upsert([['user_id' => $r->user()->id] + $data], ['user_id', 'kind'], ['local_time', 'timezone', 'enabled']);

        return $this->reminders($r);
    }

    public function notifications(Request $r)
    {
        return new ApiResult($r->user()->notificationEvents()->latest('scheduled_at')->paginate(30));
    }
}
