<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\MoveRequest;
use App\Http\Requests\SetRequest;
use App\Http\Resources\ApiResult;
use App\Services\TrainingReports;
use App\Services\WorkoutCalendar;
use App\Services\WorkoutLedger;
use App\Services\WorkoutReplacement;
use App\Services\WorkoutResults;
use Illuminate\Http\Request;

class WorkoutController
{
    public function plans(Request $r)
    {
        return new ApiResult($r->user()->trainingPlans()->with('goal')->latest()->paginate(30));
    }

    public function current(Request $r, TrainingReports $reports)
    {
        $day = $reports->today($r->user())->toDateString();

        return new ApiResult($r->user()->trainingPlans()->with(['goal', 'workoutSessions' => fn ($q) => $q->with(TrainingReports::DETAILS)])->where('status', 'active')->where('starts_on', '<=', $day)->where('ends_on', '>=', $day)->orderByDesc('version')->first());
    }

    public function plan(Request $r, string $id)
    {
        return new ApiResult($r->user()->trainingPlans()->with(['goal', 'workoutSessions' => fn ($q) => $q->with(TrainingReports::DETAILS)])->findOrFail($id));
    }

    public function today(Request $r, TrainingReports $reports)
    {
        return new ApiResult($r->user()->workoutSessions()->with(TrainingReports::DETAILS)->where('scheduled_date', $reports->today($r->user())->toDateString())->whereNotIn('status', ['cancelled', 'rescheduled'])->first());
    }

    public function week(Request $r, TrainingReports $reports)
    {
        return new ApiResult($reports->week($r->user())->with(TrainingReports::DETAILS)->get());
    }

    public function show(Request $r, string $id)
    {
        return new ApiResult($r->user()->workoutSessions()->with(TrainingReports::DETAILS)->findOrFail($id));
    }

    public function start(Request $r, string $id, WorkoutLedger $ledger)
    {
        return new ApiResult($ledger->start($r->user(), $id)->load(TrainingReports::DETAILS));
    }

    public function complete(Request $r, string $id, WorkoutLedger $ledger)
    {
        return new ApiResult($ledger->complete($r->user(), $id));
    }

    public function sets(SetRequest $r, string $id, WorkoutResults $results, ?string $setId = null)
    {
        return new ApiResult($results->record($r->user(), $id, $r->validated(), $setId));
    }

    public function replace(Request $r, string $id, string $assignment, WorkoutReplacement $service)
    {
        $data = $r->validate(['operation_id' => 'required|uuid', 'expected_exercise_id' => 'required|uuid']);

        return new ApiResult($service->replace($r->user(), $id, $assignment, $data['expected_exercise_id'], $data['operation_id']));
    }

    public function progress(Request $r, string $id, WorkoutResults $results)
    {
        return new ApiResult($results->progress($r->user()->workoutSessions()->findOrFail($id)));
    }

    public function move(MoveRequest $r, string $id, WorkoutCalendar $calendar)
    {
        return new ApiResult($calendar->change($r->user(), $id, $r->validated('date'), $r->validated('reason')));
    }
}
