<?php

use App\Models\AiGeneration;
use App\Models\PenaltyRule;
use App\Models\User;
use App\Services\AI\GeminiClient;
use App\Services\WorkoutLedger;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

Artisan::command('fit-home:gemini-check', function (GeminiClient $client) {
    $start = hrtime(true);
    $this->line('Clave: '.(config('gemini.key') ? 'presente' : 'ausente').'; modelo: '.(config('gemini.model') ? 'configurado' : 'ausente'));
    try {
        $client->verifyModel();
        $this->info('Modelo disponible con generateContent.');
        [$text,$usage] = $client->generate('Return the requested JSON with ok equal to true.', [], ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok']], 512, 1);
        if (json_decode($text, true) !== ['ok' => true]) {
            throw new HttpException(502, 'invalid_json');
        }
        $this->info('JSON verificado. Consumo: '.json_encode($usage));

        return 0;
    } catch (HttpException $e) {
        $this->error('Resultado: '.$e->getMessage());

        return 1;
    } finally {
        $this->line('Tiempo: '.round((hrtime(true) - $start) / 1e6).' ms');
    }
})->purpose('Comprobar el modelo configurado y una generación JSON real acotada');

Schedule::call(fn () => AiGeneration::where('kind', 'training_plan')->where('status', 'processing')->where('requested_at', '<=', now()->subMinutes(3))->update(['status' => 'failed', 'error_code' => 'interrupted']))->name('fit:recover-generations')->everyMinute();
