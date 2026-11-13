<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, Throwable $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return $response;
            }
            $status = $e instanceof DomainException || $e instanceof UniqueConstraintViolationException ? 409 : $response->getStatusCode();
            $message = match ($status) {
                401 => 'Credencial inválida o vencida.',403 => 'Acceso denegado.',404 => 'Recurso no encontrado.',409 => $e instanceof DomainException ? $e->getMessage() : 'Conflicto con un registro existente.',422 => 'Revisa los datos enviados.',429 => 'Demasiadas solicitudes.',503 => 'Servicio externo no disponible.',502 => 'Respuesta externa inválida.',default => 'No fue posible completar la operación.'
            };
            $errors = $e instanceof ValidationException ? $e->errors() : (object) [];

            return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $status, array_intersect_key($response->headers->all(), array_flip(['retry-after'])));
        });
        $exceptions->dontReport([DomainException::class]);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
