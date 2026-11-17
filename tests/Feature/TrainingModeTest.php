<?php

use App\Models\Exercise;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Services\AI\PlanGeneration;
use App\Services\TrainingMode;
use App\Services\WorkoutLedger;
use App\Services\WorkoutResults;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LocalPostgres;

beforeEach(function () {
    $host = getenv('FIT_TEST_HOST');
    if (! $host) {
        $this->markTestSkipped('Configura FIT_TEST_HOST para probar PostgreSQL local.');
    }
    $this->instance = new LocalPostgres;
    $this->instance->create();
});

afterEach(function () {
    if (isset($this->instance)) {
        $this->instance->close();
    }
});

function modeGuest(array $overrides = []): array
{
    $guest = test()->postJson('/api/v1/guests', $overrides + [
        'name' => 'Invitado', 'age_years' => 30, 'height_cm' => 170, 'weight_kg' => 70,
        'timezone' => 'UTC', 'level' => 'advanced', 'goal' => 'strength_cardio',
        'weekly_availability' => range(1, 7), 'session_minutes' => 30, 'complete' => true,
    ])->assertCreated()->json('data');

    return [User::where('public_id', $guest['public_id'])->sole(), [
        'X-Guest-Id' => $guest['public_id'], 'Authorization' => 'Bearer '.$guest['token'],
    ]];
}

function modePlan(User $user, ?string $mode, string $difficulty = 'intermediate', ?string $startsOn = null): TrainingPlan
{
    return $user->trainingPlans()->save((new TrainingPlan)->forceFill([
        'starts_on' => $startsOn ?? today()->subWeek(), 'ends_on' => today()->addWeek(),
        'training_difficulty' => $difficulty, 'training_mode' => $mode, 'status' => 'active',
    ]))->refresh();
}

function modeSession(TrainingPlan $plan, string $status = 'scheduled', ?string $date = null): WorkoutSession
{
    return $plan->workoutSessions()->save((new WorkoutSession)->forceFill([
        'user_id' => $plan->user_id, 'scheduled_date' => $date ?? today(), 'timezone' => 'UTC',
        'kind' => 'strength', 'status' => $status, 'cutoff_at' => today()->endOfDay(),
    ]))->refresh();
}

function modeItem(WorkoutSession $session, string $slug, int $sets = 3, int $reps = 5): WorkoutExercise
{
    return $session->workoutExercises()->save((new WorkoutExercise)->forceFill([
        'exercise_id' => Exercise::where('slug', $slug)->sole()->id, 'position' => 1,
        'sets' => $sets, 'base_reps' => $reps, 'prescription' => ['measurement' => 'reps'],
    ]));
}

function modeFakeGemini(array $ids, int $sets = 3, ?array &$context = null): void
{
    config(['gemini.key' => 'fixture', 'gemini.model' => 'fixture']);
    Http::preventStrayRequests();
    Http::fake(function ($request) use ($ids, $sets, &$context) {
        $context = json_decode($request['contents'][0]['parts'][0]['text'], true);
        $date = $context['today'];
        $plan = ['goal_id' => $context['goal']['id'], 'starts_on' => $date, 'ends_on' => $date, 'sessions' => [['date' => $date, 'exercises' => array_map(
            fn ($id) => ['id' => $id, 'sets' => $sets, 'reps' => 5, 'rest_seconds' => 30, 'estimated_seconds' => 20], $ids,
        )]]];

        return Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($plan)]]]]]]);
    });
}

it('exige una modalidad válida antes de generar el plan', function () {
    [$user, $headers] = modeGuest();
    $this->getJson('/api/v1/preferences', $headers)->assertOk()->assertJsonPath('data.training_mode', null);
    modeFakeGemini([Exercise::where('slug', 'sentadilla')->sole()->id]);
    $this->postJson('/api/v1/ai/training-plans/generate', ['request_key' => fake()->uuid()], $headers)
        ->assertUnprocessable()->assertJsonValidationErrors('training_mode');
    expect($user->aiGenerations()->count())->toBe(0)->and($user->trainingPlans()->count())->toBe(0);
    Http::assertNothingSent();
});

it('persiste la modalidad con máquina y adjunta la EVO 500 disponible', function () {
    [$user, $headers] = modeGuest();
    $this->putJson('/api/v1/preferences', ['training_mode' => 'machine'], $headers)
        ->assertSuccessful()->assertJsonPath('data.training_mode', 'machine');
    $this->getJson('/api/v1/preferences', $headers)->assertJsonPath('data.training_mode', 'machine');
    expect($user->fresh()->availableEquipment()->pluck('slug')->all())->toBe(['evo-500']);
    $this->putJson('/api/v1/preferences', ['training_mode' => 'machine'], $headers)->assertSuccessful();
    expect(DB::table('user_equipment')->where('user_id', $user->id)->count())->toBe(1)
        ->and($user->userPreference()->value('training_mode'))->toBe('machine');
});

it('persiste la modalidad en casa sin equipamiento', function () {
    [$user, $headers] = modeGuest();
    $this->putJson('/api/v1/preferences', ['training_mode' => 'bodyweight'], $headers)
        ->assertSuccessful()->assertJsonPath('data.training_mode', 'bodyweight');
    expect($user->fresh()->availableEquipment()->count())->toBe(0)
        ->and($user->userPreference()->value('training_mode'))->toBe('bodyweight');
});

it('rechaza modalidades inválidas y no permite tocar preferencias ajenas', function () {
    [$user, $headers] = modeGuest();
    $this->putJson('/api/v1/preferences', ['training_mode' => 'gym'], $headers)->assertUnprocessable();
    expect($user->userPreference()->value('training_mode'))->toBeNull();
    $this->putJson('/api/v1/preferences', ['training_mode' => 'machine'], $headers)->assertSuccessful();

    [, $other] = modeGuest();
    $this->putJson('/api/v1/preferences', ['training_mode' => 'bodyweight'], $other)->assertSuccessful();
    $this->getJson('/api/v1/preferences', $headers)->assertJsonPath('data.training_mode', 'machine');
    $this->putJson('/api/v1/preferences', ['training_mode' => 'bodyweight'], [
        'X-Guest-Id' => $user->public_id, 'Authorization' => $other['Authorization'],
    ])->assertUnauthorized();
});

it('filtra el catálogo elegible según la modalidad guardada', function () {
    [, $headers] = modeGuest();
    $machine = Exercise::where('slug', 'press-sentado')->sole();
    $this->putJson('/api/v1/preferences', ['training_mode' => 'bodyweight'], $headers)->assertSuccessful();
    $home = collect($this->getJson('/api/v1/exercises/eligible', $headers)->assertOk()->json('data.data'));
    expect($home)->toHaveCount(20)
        ->and($home->pluck('category')->unique()->sort()->values()->all())->toBe(['bodyweight', 'cardio'])
        ->and($home->contains(fn ($e) => $e['id'] === $machine->id))->toBeFalse();

    $this->putJson('/api/v1/preferences', ['training_mode' => 'machine'], $headers)->assertSuccessful();
    $machineIds = collect($this->getJson('/api/v1/exercises/eligible', $headers)->json('data.data'))->pluck('id');
    expect($machineIds)->toContain($machine->id)
        ->not->toContain(Exercise::where('slug', 'curl-predicador')->sole()->id);
});

it('genera con máquina y guarda la modalidad junto al plan', function () {
    [$user, $headers] = modeGuest();
    $this->putJson('/api/v1/preferences', ['training_mode' => 'machine'], $headers)->assertSuccessful();
    $press = Exercise::where('slug', 'press-sentado')->sole()->id;
    modeFakeGemini([$press], 3, $context);
    $generation = app(PlanGeneration::class)->generate($user->fresh(), fake()->uuid());

    expect($generation->status)->toBe('validated')->and($generation->training_mode)->toBe('machine');
    expect($context['training_mode'])->toBe('machine')->and(collect($context['exercises'])->pluck('id'))->toContain($press);
    $plan = $user->trainingPlans()->sole();
    expect($plan->training_mode)->toBe('machine')
        ->and($plan->workoutSessions()->sole()->workoutExercises()->sole()->exercise_id)->toBe($press);
});

it('genera en casa con cualquier dificultad sin máquinas', function (string $difficulty, int $sets) {
    [$user] = modeGuest();
    DB::table('user_preferences')->updateOrInsert(['user_id' => $user->id], ['training_mode' => 'bodyweight', 'training_difficulty' => $difficulty]);
    modeFakeGemini([Exercise::where('slug', 'sentadilla')->sole()->id], $sets, $context);
    $generation = app(PlanGeneration::class)->generate($user->fresh(), fake()->uuid());

    expect($generation->status)->toBe('validated')->and($context['training_mode'])->toBe('bodyweight')
        ->and($context['training_difficulty'])->toBe($difficulty)
        ->and(collect($context['exercises'])->pluck('category')->unique()->all())->not->toContain('machine');
    $plan = $user->trainingPlans()->sole();
    expect($plan->training_mode)->toBe('bodyweight')->and($plan->training_difficulty)->toBe($difficulty);
})->with([['easy', 2], ['intermediate', 3], ['hard', 4]]);

it('rechaza ejercicios incompatibles o inactivos', function (string $slug) {
    [$user] = modeGuest();
    DB::table('user_preferences')->updateOrInsert(['user_id' => $user->id], ['training_mode' => 'bodyweight']);
    modeFakeGemini([Exercise::where('slug', $slug)->sole()->id], 2);
    $generation = app(PlanGeneration::class)->generate($user->fresh(), fake()->uuid());

    expect($generation->status)->toBe('invalid')->and($generation->error_code)->toBe('exercise')
        ->and($user->trainingPlans()->count())->toBe(0)->and(WorkoutExercise::count())->toBe(0);
})->with(['máquina en modalidad casa' => ['press-sentado'], 'ejercicio inactivo' => ['curl-predicador']]);

it('el reemplazo en casa devuelve solo ejercicios de peso corporal', function () {
    [$user, $headers] = modeGuest();
    $plan = modePlan($user, 'bodyweight');
    $session = modeSession($plan);
    $item = modeItem($session, 'sentadilla');
    app(WorkoutLedger::class)->start($user, $session->id);

    $result = $this->postJson("/api/v1/workouts/$session->id/exercises/$item->id/replace", [
        'operation_id' => fake()->uuid(), 'expected_exercise_id' => $item->exercise_id,
    ], $headers)->assertOk()->assertJsonPath('data.replacements_remaining', 2)->json('data.assignment.exercise');

    expect($result['category'])->not->toBe('machine')->and($result['required_equipment'])->toBe([])
        ->and($plan->fresh()->training_mode)->toBe('bodyweight');
});

it('no consume cupo cuando no existe sustituto compatible', function () {
    [$user, $headers] = modeGuest();
    $plan = modePlan($user, 'bodyweight', 'easy');
    $session = modeSession($plan);
    $item = modeItem($session, 'sentadilla', 3);
    app(WorkoutLedger::class)->start($user, $session->id);

    $this->postJson("/api/v1/workouts/$session->id/exercises/$item->id/replace", [
        'operation_id' => fake()->uuid(), 'expected_exercise_id' => $item->exercise_id,
    ], $headers)->assertConflict();

    expect($session->fresh()->replacements_used)->toBe(0)->and($item->fresh()->exercise_id)->toBe($item->exercise_id);
});

it('conserva dificultad, progreso, penalizaciones y modalidad del plan al cambiar la preferencia', function () {
    [$user, $headers] = modeGuest();
    $plan = modePlan($user, 'bodyweight', 'hard', today()->subWeek());
    $session = modeSession($plan);
    $item = modeItem($session, 'sentadilla', 2);
    app(WorkoutLedger::class)->start($user, $session->id);
    app(WorkoutResults::class)->record($user, $session->id, ['workout_exercise_id' => $item->id, 'set_number' => 1, 'reps' => 5]);

    $missedPlan = modePlan($user, 'bodyweight', 'hard', today()->subMonth());
    $missed = modeSession($missedPlan, 'missed', today()->subDay()->toDateString());
    DB::table('penalties')->insert([
        'user_id' => $user->id, 'origin_session_id' => $missed->id, 'missed_on' => today()->subDay(),
        'status' => 'pending', 'activated_at' => now(), 'rule_id' => DB::table('penalty_rules')->value('id'),
    ]);

    $this->putJson('/api/v1/preferences', ['training_mode' => 'machine'], $headers)->assertSuccessful();

    expect($plan->fresh()->training_mode)->toBe('bodyweight')->and($plan->fresh()->training_difficulty)->toBe('hard')
        ->and($session->fresh()->status)->toBe('in_progress')->and($item->exerciseSetLogs()->count())->toBe(1)
        ->and(DB::table('penalties')->where('origin_session_id', $missed->id)->exists())->toBe(true)
        ->and($user->userPreference()->value('training_mode'))->toBe('machine');
});

it('mantiene la modalidad del plan en sesiones activas tras cambiar a máquina', function () {
    [$user, $headers] = modeGuest();
    $plan = modePlan($user, 'bodyweight');
    $session = modeSession($plan);
    $item = modeItem($session, 'sentadilla');
    app(WorkoutLedger::class)->start($user, $session->id);
    $this->putJson('/api/v1/preferences', ['training_mode' => 'machine'], $headers)->assertSuccessful();

    $replacement = $this->postJson("/api/v1/workouts/$session->id/exercises/$item->id/replace", [
        'operation_id' => fake()->uuid(), 'expected_exercise_id' => $item->exercise_id,
    ], $headers)->assertOk()->json('data.assignment.exercise');

    expect($replacement['category'])->not->toBe('machine')
        ->and(TrainingMode::forPlan($plan->fresh()))->toBe('bodyweight');
});

it('deriva la modalidad de los planes antiguos sin romperlos', function () {
    [$user, $headers] = modeGuest();
    $plan = modePlan($user, null);
    $session = modeSession($plan);
    $item = modeItem($session, 'press-sentado');
    expect(TrainingMode::forPlan($plan->fresh()))->toBe('machine');
    app(WorkoutLedger::class)->start($user, $session->id);

    $this->postJson("/api/v1/workouts/$session->id/exercises/$item->id/replace", [
        'operation_id' => fake()->uuid(), 'expected_exercise_id' => $item->exercise_id,
    ], $headers)->assertOk();

    $home = modePlan($user, null, 'intermediate', today()->subMonth());
    $homeSession = modeSession($home, 'scheduled', today()->addDay()->toDateString());
    expect(TrainingMode::forPlan($homeSession->trainingPlan))->toBe('bodyweight');
});

it('mantiene el contrato JSON anterior al añadir la modalidad', function () {
    [$user, $headers] = modeGuest();
    $this->putJson('/api/v1/preferences', ['training_mode' => 'bodyweight', 'training_difficulty' => 'easy'], $headers)->assertSuccessful();
    $prefs = $this->getJson('/api/v1/preferences', $headers)->assertOk()->json('data');
    expect($prefs)->toHaveKeys(['training_difficulty', 'training_mode', 'units', 'daily_cutoff', 'settings'])
        ->and($prefs['training_difficulty'])->toBe('easy')->and($prefs['training_mode'])->toBe('bodyweight');

    $this->putJson('/api/v1/preferences', ['training_difficulty' => 'hard'], $headers)->assertSuccessful()
        ->assertJsonPath('data.training_mode', 'bodyweight');
    expect($user->userPreference()->value('training_mode'))->toBe('bodyweight');
});

it('aplica y revierte la migración conservando registros existentes', function () {
    [$machineUser] = modeGuest();
    [$homeUser] = modeGuest();
    $machineUser->availableEquipment()->attach(DB::table('equipment')->where('slug', 'evo-500')->value('id'));
    DB::table('user_preferences')->insert(['user_id' => $machineUser->id, 'units' => 'imperial', 'training_mode' => null]);
    DB::table('user_preferences')->insert(['user_id' => $homeUser->id, 'training_mode' => null]);
    $plan = modePlan($machineUser, 'machine');
    $session = modeSession($plan);
    modeItem($session, 'press-sentado');

    $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
    expect(Schema::hasColumn('user_preferences', 'training_mode'))->toBeFalse()
        ->and(Schema::hasColumn('training_plans', 'training_mode'))->toBeFalse();

    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    expect(Schema::hasColumn('user_preferences', 'training_mode'))->toBeTrue()
        ->and(DB::table('user_preferences')->where('user_id', $machineUser->id)->value('training_mode'))->toBe('machine')
        ->and(DB::table('user_preferences')->where('user_id', $homeUser->id)->value('training_mode'))->toBe('bodyweight')
        ->and(DB::table('user_preferences')->where('user_id', $machineUser->id)->value('units'))->toBe('imperial')
        ->and(TrainingPlan::whereKey($plan->id)->exists())->toBeTrue()
        ->and(WorkoutSession::whereKey($session->id)->exists())->toBeTrue()
        ->and(WorkoutExercise::where('workout_session_id', $session->id)->count())->toBe(1);
});
