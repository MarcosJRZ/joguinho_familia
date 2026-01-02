<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Desabilitar verificações de CORS padrão do Laravel
        $middleware->remove(\Illuminate\Http\Middleware\HandleCors::class);

        // Adicionar middleware personalizado para desabilitar CORS
        $middleware->append(\App\Http\Middleware\DisableCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
