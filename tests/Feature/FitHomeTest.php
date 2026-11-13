<?php

use App\Models\Exercise;
use App\Models\MealEntry;
use App\Models\Penalty;
use App\Models\PenaltyRule;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Models\WeightLog;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Services\ExerciseCatalog;
use App\Services\GuestIdentity;
use App\Services\WorkoutCalendar;
use App\Services\WorkoutLedger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $host = getenv('FIT_TEST_HOST');
    if (! $host) {
        $this->markTestSkipped('Configura FIT_TEST_HOST para probar PostgreSQL local.');
    }
    if (! in_array($host, ['localhost', '127.0.0.1'], true) && ! str_starts_with($host, '/')) {
        throw new RuntimeException('Se requiere un PostgreSQL local.');
    }
    $config = [
        'driver' => 'pgsql', 'host' => $host, 'database' => 'fit_home_test',
        'port' => getenv('FIT_TEST_PORT') ?: 5432, 'username' => getenv('FIT_TEST_USER') ?: 'postgres',
        'password' => getenv('FIT_TEST_PASSWORD') ?: '', 'sslmode' => 'disable', 'timezone' => 'UTC', 'search_path' => 'public',
    ];
    config(['database.default' => 'fit_test', 'database.connections.fit_test' => $config]);
    Artisan::call('migrate', ['--force' => true]);
    $this->seed();
    $this->user = User::create(['name' => 'Invitado'])->refresh();
    $this->plan = fitPlan($this->user);
    $this->ledger = app(WorkoutLedger::class);
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
        $workers = [];
        DB::beginTransaction();
        try {
            User::whereKey($this->user->id)->lockForUpdate()->sole();
            for ($i = 0; $i < 2; $i++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/ledger-worker.php'), $operation, (string) $this->user->id, $today->id]);
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
