<?php

use App\Models\Exercise;
use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\MealEntryItem;
use App\Models\Penalty;
use App\Models\PenaltyRule;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Models\WeightLog;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Services\AI\GeminiClient;
use App\Services\AI\PlanGeneration;
use App\Services\ExerciseCatalog;
use App\Services\GeneratedPlanImporter;
use App\Services\GuestIdentity;
use App\Services\NutritionTotals;
use App\Services\WorkoutCalendar;
use App\Services\WorkoutLedger;
use App\Services\WorkoutResults;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Tests\Support\LocalPostgres;

beforeEach(function () {
    $host = getenv('FIT_TEST_HOST');
    if (! $host) {
        $this->markTestSkipped('Configura FIT_TEST_HOST para probar PostgreSQL local.');
    }
    $this->instance = new LocalPostgres;
    $this->instance->create();
    $this->user = User::create(['name' => 'Invitado'])->refresh();
    $this->plan = fitPlan($this->user);
    $this->ledger = app(WorkoutLedger::class);
});

afterEach(function () {
    if (isset($this->instance)) {
        $this->instance->close();
    }
});

function fitPlan(User $user): TrainingPlan
{
    return $user->trainingPlans()->save((new TrainingPlan)->forceFill([
        'starts_on' => today()->subMonth(), 'ends_on' => today()->addMonth(),
    ]))->refresh();
}

function fitSession(TrainingPlan $plan, int $daysAgo, string $kind = 'strength', string $status = 'scheduled'): WorkoutSession
{
    $date = today()->subDays($daysAgo);

    return $plan->workoutSessions()->save((new WorkoutSession)->forceFill([
        'scheduled_date' => $date, 'kind' => $kind, 'status' => $status, 'user_id' => $plan->user_id,
        'timezone' => 'UTC', 'cutoff_at' => $date->copy()->endOfDay(),
    ]))->refresh();
}

function fitRecordWorkout(User $user, WorkoutSession $session): void
{
    $item = $session->workoutExercises()->save((new WorkoutExercise)->forceFill(['exercise_id' => Exercise::where('slug', 'sentadilla')->sole()->id, 'position' => 1, 'sets' => 1, 'base_reps' => 5, 'prescription' => ['measurement' => 'reps']]));
    app(WorkoutLedger::class)->start($user, $session->id);
    app(WorkoutResults::class)->record($user, $session->id, ['workout_exercise_id' => $item->id, 'set_number' => 1, 'reps' => 5]);
}

it('carga los 26 ejercicios y preserva sus identificadores y relaciones al repetir seeders', function () {
    $slugs = explode(' ', 'press-sentado mariposa jalon remo extension-piernas curl-predicador flexion flexion-rodillas flexion-inclinada sentadilla zancada zancada-atras puente talones plancha plancha-lateral dead-bug bird-dog jumping-jacks salto-estrella escaladores rodillas-altas marcha burpee sentadilla-salto jacks-sin-salto');
    $ids = Exercise::whereIn('slug', $slugs)->orderBy('slug')->pluck('id')->all();
    $this->seed();
    expect($ids)->toHaveCount(26)->toBe(Exercise::whereIn('slug', $slugs)->orderBy('slug')->pluck('id')->all())
        ->and(DB::table('exercise_equipment')->count())->toBe(6)
        ->and(DB::table('exercise_muscle_groups')->count())->toBe(52)
        ->and(Exercise::where('slug', 'curl-predicador')->sole()->active)->toBeFalse();
});

it('filtra por equipamiento, nivel y restricciones', function () {
    DB::table('user_profiles')->insert(['user_id' => $this->user->id, 'level' => 'beginner']);
    $catalog = app(ExerciseCatalog::class);
    expect($catalog->eligible($this->user)->where('category', 'machine')->count())->toBe(0);
    $this->user->availableEquipment()->attach(DB::table('equipment')->where('slug', 'evo-500')->value('id'));
    expect($catalog->eligible($this->user)->where('category', 'machine')->count())->toBe(5);
    DB::table('user_health_constraints')->insert(['user_id' => $this->user->id, 'description' => 'Declarada', 'avoided_movements' => '["press"]']);
    expect($catalog->eligible($this->user->fresh())->whereIn('slug', ['press-sentado', 'burpee', 'curl-predicador'])->exists())->toBeFalse();
});

it('rechaza un ejercicio inexistente y referencias entre propietarios', function () {
    $other = User::create(['name' => 'Otro']);
    $otherPlan = fitPlan($other);
    $session = fitSession($this->plan, 0);
    expect($other->trainingPlans()->sole()->is($otherPlan))->toBeTrue()
        ->and($other->workoutSessions()->count())->toBe(0);
    expect(fn () => $session->forceFill(['training_plan_id' => $otherPlan->id])->save())->toThrow(QueryException::class);
    expect(fn () => DB::table('workout_exercises')->insert(['workout_session_id' => $session->id, 'exercise_id' => fake()->uuid(), 'sets' => 1, 'position' => 1, 'prescription' => '{}']))->toThrow(QueryException::class);
});

it('reconcilia omisiones y descuenta exactamente una deuda FIFO por jornada completada', function () {
    $oldest = fitSession($this->plan, 4);
    fitSession($this->plan, 3);
    fitSession($this->plan, 2);
    $this->ledger->reconcile($this->user);
    $this->ledger->reconcile($this->user);
    $pending = fn () => $this->user->penalties()->where('status', 'pending')->count();
    expect($pending())->toBe(3);
    $today = fitSession($this->plan, 0);
    fitRecordWorkout($this->user, $today);
    $this->ledger->complete($this->user, $today->id);
    $this->ledger->complete($this->user, $today->id);
    expect($pending())->toBe(2)->and($oldest->penaltyOriginSession->resolved_session_id)->toBe($today->id);
    $next = fitSession($this->plan, -1);
    $this->travel(3)->days();
    $this->ledger->reconcile($this->user);
    expect($pending())->toBe(3)->and($next->fresh()->status)->toBe('missed')
        ->and($this->user->notificationEvents()->count())->toBe(1);
    $this->travelBack();
});

it('excluye descansos, cancelaciones y sesiones reprogramadas', function () {
    fitSession($this->plan, 3, 'rest');
    fitSession($this->plan, 2, status: 'cancelled');
    fitSession($this->plan, 1, status: 'rescheduled');
    $this->ledger->reconcile($this->user);
    expect($this->user->penalties()->count())->toBe(0);
});

it('resuelve el corte en hora local y conserva revisiones al reprogramar', function () {
    DB::table('user_profiles')->insert(['user_id' => $this->user->id, 'timezone' => 'America/Bogota']);
    DB::table('user_preferences')->insert(['user_id' => $this->user->id, 'daily_cutoff' => '18:30']);
    $calendar = app(WorkoutCalendar::class);
    $session = $calendar->schedule($this->user, $this->plan->id, today()->addDay()->toDateString(), 'strength')->refresh();
    expect($session->cutoff_at->utc()->format('H:i'))->toBe('23:30');
    $new = $calendar->change($this->user, $session->id, today()->addDays(2)->toDateString(), 'Cambio de disponibilidad');
    expect($new->rescheduled_from_id)->toBe($session->id)->and($session->fresh()->status)->toBe('rescheduled')
        ->and($this->plan->trainingPlanRevisions()->count())->toBe(1);
});

it('limita las repeticiones y conserva las series de tiempo y el saldo', function () {
    $rule = PenaltyRule::where('slug', 'daily-missed-v1')->sole();
    $rule->forceFill(['adjustments_enabled' => true, 'max_extra_reps' => 10])->save();
    $exercise = Exercise::where('slug', 'sentadilla')->sole();
    $exercise->forceFill(['rep_limits' => ['beginner' => 16]])->save();
    try {
        DB::table('user_profiles')->insert(['user_id' => $this->user->id]);
        DB::table('user_preferences')->insert(['user_id' => $this->user->id, 'max_extra_reps' => 8, 'penalty_adjustments' => true]);
        fitSession($this->plan, 2);
        fitSession($this->plan, 1);
        $session = fitSession($this->plan, 0);
        foreach ([$exercise, Exercise::where('slug', 'plancha')->sole()] as $index => $entry) {
            $session->workoutExercises()->save((new WorkoutExercise)->forceFill(['exercise_id' => $entry->id, 'sets' => 2, 'position' => $index + 1, 'base_reps' => $index ? null : 10, 'duration_seconds' => $index ? 15 : null, 'prescription' => ['name' => $entry->name]]));
        }
        $this->ledger->start($this->user, $session->id);
        $this->ledger->start($this->user, $session->id);
        [$first,$second] = $session->workoutExercises()->orderBy('position')->get();
        expect($first->workoutPenaltyApplication->applied['base_reps'])->toBe(16)
            ->and($first->workoutPenaltyApplication->reason)->toBe('capped')
            ->and($first->base_reps)->toBe(10)->and($second->workoutPenaltyApplication->applied['base_reps'])->toBeNull()
            ->and($second->workoutPenaltyApplication->applied['duration_seconds'])->toBe(15)
            ->and($this->user->penalties()->where('status', 'pending')->count())->toBe(2);
    } finally {
        $rule->forceFill(['adjustments_enabled' => false, 'max_extra_reps' => 0])->save();
        $exercise->forceFill(['rep_limits' => []])->save();
    }
});

it('aísla las mediciones, comidas y credenciales de invitados', function () {
    $other = User::create(['name' => 'Otro'])->refresh();
    $this->user->weightLogs()->save((new WeightLog)->forceFill(['weight_kg' => '72.500', 'measured_at' => now(), 'source' => 'manual']));
    $this->user->mealEntries()->save((new MealEntry)->forceFill(['kind' => 'lunch', 'local_date' => today(), 'eaten_at' => now(), 'timezone' => 'UTC']));
    expect($other->weightLogs()->count())->toBe(0)->and($other->mealEntries()->count())->toBe(0);
    $identity = app(GuestIdentity::class);
    $secret = $identity->issue($this->user);
    expect($identity->authenticate($other->public_id, $secret))->toBeNull()
        ->and($identity->authenticate($this->user->public_id, $secret)->id)->toBe($this->user->id)
        ->and($this->user->guestAccessTokens()->sole()->token_hash)->toBe(hash('sha256', $secret));
    $this->user->guestAccessTokens()->update(['revoked_at' => now()]);
    expect($identity->authenticate($this->user->public_id, $secret))->toBeNull();
});

it('serializa procesos PostgreSQL concurrentes durante omisión y resolución', function () {
    fitSession($this->plan, 2);
    fitSession($this->plan, 1);
    $today = fitSession($this->plan, 0);
    foreach (['reconcile', 'complete'] as $operation) {
        if ($operation === 'complete') {
            fitRecordWorkout($this->user, $today);
        }
        $workers = [];
        DB::beginTransaction();
        try {
            User::whereKey($this->user->id)->lockForUpdate()->sole();
            for ($i = 0; $i < 2; $i++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/ledger-worker.php'), $operation, (string) $this->user->id, $today->id, DB::connection()->getDatabaseName()]);
                $worker->start();
                $workers[] = $worker;
            }
            $until = microtime(true) + 8;
            do {
                $waiting = DB::selectOne("SELECT count(*) AS count FROM pg_stat_activity WHERE datname=current_database() AND application_name='fit-ledger-worker' AND wait_event_type='Lock'")->count;
                DB::statement('SELECT pg_stat_clear_snapshot()');
                if ((int) $waiting === 2) {
                    break;
                }
                usleep(25000);
            } while (microtime(true) < $until);
        } finally {
            DB::commit();
        }
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->getErrorOutput().$worker->getOutput())->toBe('')->and($worker->isSuccessful())->toBeTrue();
        }
        expect((int) $waiting)->toBe(2);
    }
    expect($this->user->penalties()->count())->toBe(2)
        ->and(Penalty::where('resolved_session_id', $today->id)->count())->toBe(1)
        ->and($this->user->penalties()->where('status', 'pending')->count())->toBe(1);
});

it('limita las migraciones a las tablas de la aplicación y activa RLS', function () {
    foreach (glob(database_path('migrations/*.php')) as $path) {
        expect(file_get_contents($path))->not->toMatch('/\b(?:auth|storage)\s*\./i');
    }
    $unprotected = DB::select("select relname from pg_class where relnamespace='public'::regnamespace and relkind='r' and not relrowsecurity");
    expect($unprotected)->toBe([]);
});

it('calcula totales con la corrección más reciente y conserva el original', function () {
    $meal = $this->user->mealEntries()->save((new MealEntry)->forceFill(['kind' => 'lunch', 'local_date' => today(), 'eaten_at' => now(), 'timezone' => 'UTC']));
    $portion = ['name' => 'Porción de prueba', 'quantity' => 1, 'unit' => 'unidad', 'kcal' => 100, 'protein_g' => 2, 'carbs_g' => 20, 'fat_g' => 1, 'source' => 'etiqueta', 'estimated_at' => now()];
    $original = $meal->mealEntryItems()->save((new MealEntryItem)->forceFill($portion));
    expect(fn () => $original->forceFill(['kcal' => 90])->save())->toThrow(QueryException::class);
    $meal->mealEntryItems()->save((new MealEntryItem)->forceFill(['supersedes_id' => $original->id, 'kcal' => 90] + $portion));
    $totals = app(NutritionTotals::class);
    expect($totals->forDay($this->user, today()->toDateString())->kcal)->toBe('90.000')
        ->and($totals->forMeal($this->user, $meal->id)->protein_g)->toBe('2.000')
        ->and($original->fresh()->kcal)->toBe('100.000');
});

it('acepta JSON compatible y revierte el plan entero al rechazar un ejercicio', function () {
    $date = today()->addDay();
    DB::table('user_profiles')->insert(['user_id' => $this->user->id]);
    DB::table('user_onboarding')->insert(['user_id' => $this->user->id, 'session_minutes' => 20, 'weekly_availability' => json_encode([$date->dayOfWeekIso])]);
    DB::table('user_preferences')->insert(['user_id' => $this->user->id, 'settings' => '{"plan_limits":{"max_sets":2,"max_seconds_per_set":30}}']);
    $goal = DB::table('user_goals')->insertGetId(['user_id' => $this->user->id, 'kind' => 'strength_cardio', 'start_date' => today()]);
    $generation = fn () => DB::table('ai_generations')->insertGetId(['user_id' => $this->user->id, 'kind' => 'training_plan', 'schema_version' => '1', 'model' => 'test', 'prompt_version' => '1', 'status' => 'received', 'requested_at' => now()]);
    $item = ['id' => Exercise::where('slug', 'plancha')->sole()->id, 'sets' => 2, 'seconds' => 15, 'rest_seconds' => 20, 'estimated_seconds' => 15];
    $plan = ['goal_id' => $goal, 'starts_on' => $date->toDateString(), 'ends_on' => $date->toDateString(), 'sessions' => [['date' => $date->toDateString(), 'exercises' => [$item]]]];
    $bad = $plan;
    $bad['sessions'][0]['exercises'][] = ['id' => Exercise::where('slug', 'press-sentado')->sole()->id] + $item;
    $importer = app(GeneratedPlanImporter::class);
    expect($importer->import($this->user, $generation(), json_encode($bad))->validation_status)->toBe('rejected')
        ->and($this->user->workoutSessions()->count())->toBe(0);
    $id = $generation();
    $result = $importer->import($this->user, $id, json_encode($plan));
    expect($result->validation_status)->toBe('accepted')
        ->and($importer->import($this->user, $id, json_encode($plan))->id)->toBe($result->id)
        ->and($this->user->workoutSessions()->count())->toBe(1);
});

it('crea, rota y expira credenciales por HTTP y conserva onboarding por etapas', function () {
    $guest = $this->postJson('/api/v1/guests', ['name' => 'Ana', 'weight_kg' => 72])->assertCreated()->json('data');
    $headers = ['X-Guest-Id' => $guest['public_id'], 'Authorization' => 'Bearer '.$guest['token']];
    $this->getJson('/api/v1/profile', ['X-Guest-Id' => $guest['public_id'], 'Authorization' => 'Bearer wrong'])->assertUnauthorized();
    $this->postJson('/api/v1/onboarding', ['height_cm' => 170, 'age_years' => 30, 'goal' => 'strength_cardio'], $headers)->assertOk();
    $this->postJson('/api/v1/onboarding', ['session_minutes' => 20, 'weekly_availability' => [1, 3], 'complete' => true], $headers)->assertOk();
    $this->getJson('/api/v1/weight-logs', $headers)->assertJsonPath('data.total', 1);
    $this->getJson('/api/v1/dashboard', $headers)->assertOk()->assertJsonPath('data.name', 'Ana');
    $this->getJson('/api/v1/exercises?level=beginner&category=machine', $headers)->assertOk()->assertJsonPath('data.total', 5);
    $new = $this->postJson('/api/v1/guests/rotate-token', [], $headers)->assertOk()->json('data.token');
    $this->getJson('/api/v1/profile', $headers)->assertUnauthorized();
    $headers['Authorization'] = 'Bearer '.$new;
    $this->getJson('/api/v1/profile', $headers)->assertOk();
    DB::table('guest_access_tokens')->where('token_hash', hash('sha256', $new))->update(['expires_at' => now()->subMinute()]);
    $this->getJson('/api/v1/profile', $headers)->assertUnauthorized();
});

it('exige series válidas HTTP y aísla jornadas', function () {
    $headers = ['X-Guest-Id' => $this->user->public_id, 'Authorization' => 'Bearer '.app(GuestIdentity::class)->issue($this->user)];
    $session = fitSession($this->plan, 0);
    $url = '/api/v1/workouts/'.$session->id;
    $this->postJson("$url/complete", [], $headers)->assertConflict();
    $this->postJson("$url/start", [], $headers)->assertOk();
    $this->postJson("$url/complete", [], $headers)->assertConflict();
    $item = $session->workoutExercises()->save((new WorkoutExercise)->forceFill(['exercise_id' => Exercise::where('slug', 'sentadilla')->sole()->id, 'position' => 1, 'sets' => 2, 'base_reps' => 5, 'prescription' => ['measurement' => 'reps']]));
    $payload = ['workout_exercise_id' => $item->id, 'set_number' => 1, 'reps' => 5];
    $this->postJson("$url/sets", $payload, $headers)->assertCreated();
    $this->postJson("$url/sets", $payload, $headers)->assertOk();
    $this->postJson("$url/complete", [], $headers)->assertConflict();
    $this->postJson("$url/sets", ['set_number' => 2] + $payload, $headers)->assertCreated();
    $this->postJson("$url/complete", [], $headers)->assertOk()->assertJsonPath('data.status', 'completed');
    $other = User::create(['name' => 'Otro'])->refresh();
    $this->getJson($url, ['X-Guest-Id' => $other->public_id, 'Authorization' => 'Bearer '.app(GuestIdentity::class)->issue($other)])->assertNotFound();
});

it('registra alimentación desconocida, corrige porción y consulta estadísticas HTTP', function () {
    $headers = ['X-Guest-Id' => $this->user->public_id, 'Authorization' => 'Bearer '.app(GuestIdentity::class)->issue($this->user)];
    $id = $this->postJson('/api/v1/meals', ['kind' => 'lunch', 'eaten_at' => now()->toIso8601String()], $headers)->assertCreated()->json('data.id');
    $payload = ['name' => 'Arroz', 'quantity' => 150, 'unit' => 'g'];
    $url = "/api/v1/meals/$id/items";
    $item = $this->postJson($url, $payload, $headers)->assertCreated()->json('data.id');
    $this->postJson("$url/$item/correct", ['quantity' => 200] + $payload, $headers)->assertCreated();
    $this->postJson("$url/$item/correct", $payload, $headers)->assertConflict();
    $this->getJson('/api/v1/nutrition/today', $headers)->assertOk()->assertJsonPath('data.kcal', null);
    $this->putJson('/api/v1/notifications/preferences', ['kind' => 'training', 'timezone' => 'America/Bogota', 'local_time' => '09:30', 'enabled' => true], $headers)->assertOk();
    foreach (['statistics/overview', 'statistics/weight', 'statistics/workouts', 'statistics/streaks', 'penalties/summary', 'nutrition/history', 'workouts/week', 'notifications'] as $path) {
        $this->getJson("/api/v1/$path", $headers)->assertOk()->assertJsonPath('success', true);
    }
});

it('valida Gemini por HTTP y conserva idempotencia, errores y aislamiento', function (string $case, int $status) {
    $guest = $this->postJson('/api/v1/guests', ['age_years' => 28, 'height_cm' => 172, 'weight_kg' => 70, 'timezone' => 'UTC', 'level' => 'beginner', 'goal' => 'strength_cardio', 'weekly_availability' => [1, 2, 3, 4, 5, 6, 7], 'session_minutes' => 20, 'complete' => true])->assertCreated()->json('data');
    $user = User::where('public_id', $guest['public_id'])->sole();
    $headers = ['X-Guest-Id' => $guest['public_id'], 'Authorization' => 'Bearer '.$guest['token']];
    config(['gemini.key' => $case === 'configuration' ? null : 'fixture', 'gemini.model' => 'fixture-model']);
    $date = today()->addDay()->toDateString();
    $item = ['id' => Exercise::where('slug', $case === 'equipment' ? 'press-sentado' : 'sentadilla')->sole()->id, 'sets' => 1, 'reps' => 5, 'rest_seconds' => 20, 'estimated_seconds' => 15];
    if ($case === 'nonexistent') {
        $item['id'] = fake()->uuid();
    }
    if ($case === 'duration') {
        $item['estimated_seconds'] = 9000;
    }
    if ($case === 'availability') {
        $user->userOnboarding->forceFill(['weekly_availability' => [today()->dayOfWeekIso]])->save();
    }
    $plan = ['goal_id' => $user->userGoals()->sole()->id, 'starts_on' => $date, 'ends_on' => $date, 'sessions' => [['date' => $date, 'exercises' => [$item]]]];
    $response = ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $case === 'malformed' ? 'invalid' : json_encode($plan)]]]]]];
    Http::preventStrayRequests();
    Http::fake(['*' => match ($case) {
        'timeout' => Http::failedConnection(),'provider' => Http::response([], 503),default => Http::response($response)
    }]);
    $data = ['request_key' => fake()->uuid()];
    $url = '/api/v1/ai/training-plans/generate';
    $this->postJson($url, $data, $headers)->assertStatus($status);
    $this->postJson($url, $data, $headers)->assertStatus($status);
    expect($user->aiGenerations()->count())->toBe(1)->and($user->trainingPlans()->count())->toBe($status === 200 ? 1 : 0);
    if ($status === 200) {
        Http::assertSentCount(1);
        $this->postJson($url, ['request_key' => fake()->uuid()], $headers)->assertConflict();
    }
})->with([['valid', 200], ['nonexistent', 422], ['equipment', 422], ['malformed', 422], ['duration', 422], ['availability', 422], ['timeout', 503], ['provider', 503], ['configuration', 503]]);

it('conserva resultados parciales al vencer y rechaza series ajenas', function () {
    $session = fitSession($this->plan, 0);
    $item = $session->workoutExercises()->save((new WorkoutExercise)->forceFill(['exercise_id' => Exercise::where('slug', 'plancha')->sole()->id, 'position' => 1, 'sets' => 2, 'duration_seconds' => 15, 'prescription' => ['measurement' => 'time']]));
    $this->ledger->start($this->user, $session->id);
    $headers = ['X-Guest-Id' => $this->user->public_id, 'Authorization' => 'Bearer '.app(GuestIdentity::class)->issue($this->user)];
    $url = '/api/v1/workouts/'.$session->id.'/sets';
    $data = ['workout_exercise_id' => $item->id, 'set_number' => 1];
    $this->postJson($url, $data + ['reps' => 15], $headers)->assertUnprocessable();
    $this->postJson($url, ['workout_exercise_id' => fake()->uuid()] + $data + ['duration_seconds' => 15], $headers)->assertNotFound();
    $this->postJson($url, $data + ['duration_seconds' => 15], $headers)->assertCreated();
    $this->travel(2)->days();
    $this->ledger->reconcile($this->user);
    expect($session->fresh()->status)->toBe('missed')->and($item->exerciseSetLogs()->count())->toBe(1)->and($this->user->penalties()->count())->toBe(1);
    $this->travelBack();
});

it('solo usa referencias verificables en las comidas y aísla el historial', function () {
    $food = FoodItem::where('slug', 'fixture-label')->first() ?? FoodItem::forceCreate(['slug' => 'fixture-label', 'name' => 'Prueba', 'reference_unit' => 'g', 'reference_quantity' => 100, 'kcal' => 50, 'protein_g' => 2, 'carbs_g' => 5, 'fat_g' => 1, 'source' => 'fixture', 'verification' => 'verified', 'source_date' => today()]);
    $guest = $this->postJson('/api/v1/guests')->json('data');
    $headers = ['X-Guest-Id' => $guest['public_id'], 'Authorization' => 'Bearer '.$guest['token']];
    $id = $this->postJson('/api/v1/meals', ['kind' => 'breakfast', 'eaten_at' => now()->toIso8601String()], $headers)->json('data.id');
    $url = "/api/v1/meals/$id/items";
    $this->postJson($url, ['food_item_id' => $food->id, 'quantity' => -1, 'unit' => 'g'], $headers)->assertUnprocessable();
    $this->postJson($url, ['food_item_id' => $food->id, 'quantity' => 150, 'unit' => 'g'], $headers)->assertCreated();
    $this->getJson('/api/v1/nutrition/today', $headers)->assertJsonPath('data.kcal', '75.000');
    $other = ['X-Guest-Id' => $this->user->public_id, 'Authorization' => 'Bearer '.app(GuestIdentity::class)->issue($this->user)];
    $this->getJson("/api/v1/meals/$id", $other)->assertNotFound();
    $this->postJson('/api/v1/weight-logs', ['weight_kg' => 75, 'measured_at' => now()->toIso8601String()], $headers)->assertCreated();
    $this->getJson('/api/v1/statistics/weight', $headers)->assertJsonPath('data.latest.weight_kg', '75.000');
    $this->getJson('/api/v1/weight-logs', $other)->assertJsonPath('data.total', 0);
});

it('persiste una falla inesperada y recupera una solicitud interrumpida', function () {
    $guest = $this->postJson('/api/v1/guests', ['age_years' => 28, 'height_cm' => 172, 'weight_kg' => 70, 'goal' => 'strength_cardio', 'weekly_availability' => [1], 'session_minutes' => 20, 'complete' => true])->json('data');
    $headers = ['X-Guest-Id' => $guest['public_id'], 'Authorization' => 'Bearer '.$guest['token']];
    $mock = Mockery::mock(GeminiClient::class);
    $mock->shouldReceive('generate')->once()->andThrow(new RuntimeException('private diagnostic'));
    app()->instance(GeminiClient::class, $mock);
    $payload = ['request_key' => fake()->uuid()];
    $url = '/api/v1/ai/training-plans/generate';
    $response = $this->postJson($url, $payload, $headers)->assertStatus(503)->assertJsonPath('errors.reason', 'internal_error');
    $id = $response->json('errors.generation_id');
    $this->getJson("/api/v1/ai/generations/$id", $headers)->assertJsonPath('data.status', 'failed');
    DB::table('ai_generations')->where('id', $id)->update(['status' => 'processing', 'requested_at' => now()->subMinutes(5)]);
    $this->postJson($url, $payload, $headers)->assertStatus(503)->assertJsonPath('errors.reason', 'interrupted');
    expect(DB::table('training_plans')->where('source', 'ai')->count())->toBe(0);
});

it('analiza sin guardar y confirma solo una porción con referencia verificada', function () {
    $path = '/api/v1/ai/nutrition/analyze';
    $this->postJson($path, ['description' => 'arroz'])->assertUnauthorized();
    $headers = ['X-Guest-Id' => $this->user->public_id, 'Authorization' => 'Bearer '.app(GuestIdentity::class)->issue($this->user)];
    $this->postJson($path, ['description' => '', 'meal_type' => 'invalid'], $headers)->assertUnprocessable();
    config(['gemini.key' => 'fixture', 'gemini.model' => 'fixture']);
    Http::preventStrayRequests();
    $items = [['name' => 'Arroz cocido', 'quantity' => 0.15, 'unit' => 'kg', 'reference_slug' => 'usda-168878'], ['name' => 'Huevos', 'quantity' => 2, 'unit' => 'unidad', 'reference_slug' => 'usda-173424'], ['name' => 'Sancocho', 'reference_slug' => 'inventado']];
    $reply = ['meal_type' => 'lunch', 'items' => $items];
    Http::fake(['*' => Http::sequence()->push(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($reply)]]]]]])->push(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'invalid']]]]]])]);
    $proposed = $this->postJson($path, ['description' => 'Almorcé arroz cocido, dos huevos y sancocho.'], $headers)->assertOk()
        ->assertJsonPath('data.items.0.nutrients.kcal', 195)->assertJsonPath('data.items.1.confirmation', null)->assertJsonPath('data.items.2.nutrients.kcal', null)->json('data.items.0.confirmation');
    expect($this->user->mealEntries()->count())->toBe(0);
    $id = $this->postJson('/api/v1/meals', ['kind' => 'lunch', 'eaten_at' => now()->toIso8601String()], $headers)->json('data.id');
    $this->postJson("/api/v1/meals/$id/items", $proposed, $headers)->assertCreated();
    $this->getJson('/api/v1/nutrition/today', $headers)->assertJsonPath('data.kcal', '195.000');
    $this->postJson($path, ['description' => 'otro alimento'], $headers)->assertStatus(502);
    $this->seed();
    expect(FoodItem::where('slug', 'like', 'usda-%')->count())->toBe(5);
});

it('conserva una única generación activa durante la llamada y descarta respuestas tardías', function () {
    $guest = $this->postJson('/api/v1/guests', ['age_years' => 30, 'height_cm' => 170, 'weight_kg' => 70, 'goal' => 'strength_cardio', 'weekly_availability' => [1], 'session_minutes' => 20, 'complete' => true])->json('data');
    $user = User::where('public_id', $guest['public_id'])->sole();
    $key = fake()->uuid();
    config(['gemini.key' => 'fixture', 'gemini.model' => 'fixture']);
    Http::fake(function () use ($user, $key) {
        expect(DB::transactionLevel())->toBe(0);
        $service = app(PlanGeneration::class);
        expect($service->generate($user, $key)->status)->toBe('processing');
        expect(fn () => $service->generate($user, fake()->uuid()))->toThrow(DomainException::class);
        $user->aiGenerations()->update(['status' => 'failed', 'error_code' => 'interrupted']);

        return Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{}']]]]]]);
    });
    $gen = app(PlanGeneration::class)->generate($user, $key);
    expect($gen->status)->toBe('failed')->and($gen->error_code)->toBe('interrupted')
        ->and($user->trainingPlans()->count())->toBe(0)->and($user->aiGenerations()->count())->toBe(1);
});

it('revierte la importación si falla PostgreSQL al confirmar el consumo', function () {
    $guest = $this->postJson('/api/v1/guests', ['age_years' => 30, 'height_cm' => 170, 'weight_kg' => 70, 'goal' => 'strength_cardio', 'weekly_availability' => [1, 2, 3, 4, 5, 6, 7], 'session_minutes' => 20, 'complete' => true])->json('data');
    $user = User::where('public_id', $guest['public_id'])->sole();
    config(['gemini.key' => 'fixture', 'gemini.model' => 'fixture']);
    DB::statement("ALTER TABLE ai_generations ADD CHECK (jsonb_exists(usage, 'totalTokenCount') = false)");
    $day = today()->addDay()->toDateString();
    $plan = ['goal_id' => $user->userGoals()->sole()->id, 'starts_on' => $day, 'ends_on' => $day, 'sessions' => [['date' => $day, 'exercises' => [['id' => Exercise::where('slug', 'sentadilla')->sole()->id, 'sets' => 1, 'reps' => 5, 'rest_seconds' => 30, 'estimated_seconds' => 20]]]]];
    Http::fake(['*' => Http::response(['usageMetadata' => ['totalTokenCount' => 123], 'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($plan)]]]]]])]);
    $gen = app(PlanGeneration::class)->generate($user, fake()->uuid());
    expect($gen->status)->toBe('failed')->and($gen->error_code)->toBe('database')
        ->and($user->trainingPlans()->count())->toBe(0)->and($gen->aiPlanRecommendations()->count())->toBe(0);
});

it('conserva la sesión y expone la ilustración opcional con el ejercicio', function () {
    $guest = $this->postJson('/api/v1/guests', ['name' => 'Ana'])->assertCreated()->json('data');
    $headers = ['X-Guest-Id' => $guest['public_id'], 'Authorization' => 'Bearer '.$guest['token']];
    $user = User::where('public_id', $guest['public_id'])->sole();
    $session = fitSession(fitPlan($user), 0);
    fitRecordWorkout($user, $session);
    $exercise = Exercise::where('slug', 'sentadilla')->sole();
    $migration = require database_path('migrations/2026_11_16_000000_add_exercise_image_url.php');
    $migration->down();
    $migration->up();
    $this->getJson("/api/v1/exercises/$exercise->id", $headers)->assertOk()
        ->assertJsonStructure(['data' => ['image_url']])->assertJsonPath('data.image_url', null);
    $base = 'https://images.test/storage/v1/object/public/exercise-illustrations';
    config(['services.exercise_images.base_url' => $base]);
    $url = "$base/exercises/sentadilla-v1.webp";
    Http::fake([$url => Http::response('missing', 404)]);
    $this->artisan('fit:exercise-image sentadilla 1 --apply')->assertFailed();
    expect($exercise->fresh()->image_url)->toBeNull();
    $exercise->image_url = $url;
    $exercise->save();
    $this->getJson("/api/v1/workouts/$session->id", $headers)->assertOk()
        ->assertJsonPath('data.workout_exercises.0.exercise.id', $exercise->id)
        ->assertJsonPath('data.workout_exercises.0.exercise.image_url', $url)
        ->assertJsonPath('data.workout_exercises.0.exercise.instructions', $exercise->instructions)
        ->assertJsonCount(1, 'data.workout_exercises.0.exercise_set_logs');
    expect(fn () => $exercise->forceFill(['image_url' => 'http://images.test/x.webp'])->save())->toThrow(QueryException::class);
});

it('verifica el WebP público antes de aplicar una referencia versionada', function () {
    $base = 'https://images.test/storage/v1/object/public/exercise-illustrations';
    config(['services.exercise_images.base_url' => $base]);
    $url = "$base/exercises/sentadilla-v1.webp";
    // WebP mínimo autogenerado para la respuesta HTTP de prueba.
    $bytes = base64_decode('UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoEAAMAAUAmJaQAA3AA/v02aAA=');
    Http::fake([$url => Http::response($bytes, 200, ['Content-Type' => 'image/webp'])]);
    $exercise = Exercise::where('slug', 'sentadilla')->sole();
    $this->artisan('fit:exercise-image sentadilla 1')->assertSuccessful();
    expect($exercise->fresh()->image_url)->toBeNull();
    $command = 'fit:exercise-image sentadilla 1 --apply';
    $this->artisan($command.' --id='.fake()->uuid())->assertFailed();
    $this->artisan($command.' --sha256='.str_repeat('0', 64))->assertFailed();
    $old = "$base/exercises/sentadilla-v0.webp";
    $exercise->forceFill(['image_url' => $old])->save();
    $this->artisan($command)->assertFailed();
    expect($exercise->fresh()->image_url)->toBe($old);
    $this->artisan($command." --id=$exercise->id --previous-url=$old --sha256=".hash('sha256', $bytes))->assertSuccessful();
    $this->artisan($command)->assertSuccessful();
    expect($exercise->fresh()->image_url)->toBe($url);
});

it('mantiene la referencia vacía si el tipo o el contenido remoto no es WebP', function (string $type, string $body) {
    $base = 'https://images.test/storage/v1/object/public/exercise-illustrations';
    config(['services.exercise_images.base_url' => $base]);
    Http::fake(["$base/*" => Http::response($body, 200, ['Content-Type' => $type])]);
    $this->artisan('fit:exercise-image plancha 2 --apply')->assertFailed();
    expect(Exercise::where('slug', 'plancha')->sole()->image_url)->toBeNull();
})->with([
    'tipo anunciado incorrecto' => ['text/html', base64_decode('UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoEAAMAAUAmJaQAA3AA/v02aAA=')],
    'tipo anunciado correcto con contenido inválido' => ['image/webp', '<html>error</html>'],
]);
