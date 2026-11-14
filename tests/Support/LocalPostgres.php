<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LocalPostgres
{
    public static function connect(string $database): void
    {
        $host = getenv('FIT_TEST_HOST');
        if (! $host || (! in_array($host, ['localhost', '127.0.0.1'], true) && ! preg_match('#^/[a-zA-Z0-9_./-]+$#D', $host))
            || ! preg_match('/^fit_home_test(?:_[a-f0-9]{16})?$/D', $database)) {
            throw new RuntimeException('Se requiere una base de pruebas local.');
        }
        config(['database.default' => 'fit_test', 'database.connections.fit_test' => [
            'driver' => 'pgsql', 'host' => $host, 'port' => (int) (getenv('FIT_TEST_PORT') ?: 5432),
            'database' => $database, 'username' => getenv('FIT_TEST_USER') ?: 'postgres', 'password' => getenv('FIT_TEST_PASSWORD') ?: '',
            'sslmode' => 'disable', 'timezone' => 'UTC', 'search_path' => 'public',
        ]]);
        DB::purge('fit_test');
    }

    private string $database;

    public function create(): void
    {
        self::connect('fit_home_test');
        $this->database = 'fit_home_test_'.bin2hex(random_bytes(8));
        DB::statement('CREATE DATABASE '.$this->database.' TEMPLATE template0');
        self::connect($this->database);
        if (Artisan::call('migrate', ['--force' => true]) || Artisan::call('db:seed', ['--force' => true])) {
            throw new RuntimeException('Falló la preparación de la base de pruebas.');
        }
    }

    public function close(): void
    {
        if (! isset($this->database)) {
            return;
        }
        self::connect('fit_home_test');
        DB::statement('DROP DATABASE '.$this->database);
    }
}
