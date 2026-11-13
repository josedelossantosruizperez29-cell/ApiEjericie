<?php

use App\Models\User;
use App\Services\WorkoutLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$host = getenv('FIT_TEST_HOST');
if (! $host || (! str_starts_with($host, '/') && ! in_array($host, ['localhost', '127.0.0.1'], true))) {
    exit(2);
}
config(['database.default' => 'worker', 'database.connections.worker' => [
    'driver' => 'pgsql', 'host' => $host, 'port' => getenv('FIT_TEST_PORT') ?: 5432, 'database' => 'fit_home_test',
    'username' => getenv('FIT_TEST_USER') ?: 'postgres', 'password' => getenv('FIT_TEST_PASSWORD') ?: '', 'timezone' => 'UTC',
]]);
DB::statement("SET application_name='fit-ledger-worker'");
$user = User::findOrFail($argv[2]);
$ledger = app(WorkoutLedger::class);
match ($argv[1]) {
    'reconcile' => $ledger->reconcile($user),
    'complete' => $ledger->complete($user, $argv[3]),
};
