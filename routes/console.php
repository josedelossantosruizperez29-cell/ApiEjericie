<?php

use App\Models\User;
use App\Services\WorkoutLedger;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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
