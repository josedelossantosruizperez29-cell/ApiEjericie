<?php

use App\Models\PenaltyRule;
use App\Models\User;
use App\Services\WorkoutLedger;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fit:reconcile {--user=}', function (WorkoutLedger $ledger) {
    $users = User::query();
    if ($this->option('user') !== null) {
        $ledger->reconcile($users->findOrFail($this->option('user')));

        return;
    }
    $users->whereHas('workoutSessions', fn ($q) => $q->whereIn('status', ['scheduled', 'in_progress'])
        ->where('kind', '<>', 'rest')->where('cutoff_at', '<=', now()))
        ->eachById(fn ($user) => $ledger->reconcile($user));
})->purpose('Procesar jornadas vencidas en la zona horaria de cada sesión');

Schedule::command('fit:reconcile')->everyMinute();

Artisan::command('fit:penalty-limits {--enabled=} {--max-extra=}', function () {
    $data = Validator::make(['enabled' => $this->option('enabled'), 'max_extra' => $this->option('max-extra')], [
        'enabled' => 'required|in:0,1', 'max_extra' => 'required|integer|between:0,100',
    ])->validate();
    $rule = PenaltyRule::where('slug', 'daily-missed-v1')->sole();
    $rule->forceFill(['adjustments_enabled' => (bool) $data['enabled'], 'max_extra_reps' => (int) $data['max_extra']])->save();
    $this->info('Configuración global actualizada. Siguen vigentes los límites individuales y por ejercicio.');
})->purpose('Configurar el tope global de ajustes por deuda');
