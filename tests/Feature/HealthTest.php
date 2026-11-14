<?php

use Illuminate\Support\Facades\DB;

it('responde al sondeo público sin sesión ni consultas SQL', function () {
    config(['session.driver' => 'database', 'cache.default' => 'database']);
    DB::listen(fn () => throw new RuntimeException('La sonda consultó la base de datos.'));
    $response = $this->get('/health')->assertOk()->assertExactJson(['status' => 'ok', 'service' => 'fit-home-api']);
    expect($response->headers->getCookies())->toBeEmpty();
});
