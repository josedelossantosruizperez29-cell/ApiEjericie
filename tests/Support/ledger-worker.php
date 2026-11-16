<?php

use App\Models\User;
use App\Services\WorkoutLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\LocalPostgres;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
LocalPostgres::connect($argv[4]);
DB::statement("SET application_name='fit-ledger-worker'");
$user = User::findOrFail($argv[2]);
$ledger = app(WorkoutLedger::class);
match ($argv[1]) {
    'reconcile' => $ledger->reconcile($user),
    'complete' => $ledger->complete($user, $argv[3]),
    'replace' => app(\App\Services\WorkoutReplacement::class)->replace($user, $argv[3], $argv[5], $argv[6], $argv[7]),
};
