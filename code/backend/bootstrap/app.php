<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->job(new \App\Jobs\VarreduraDaPastaCompartilhadaJob)
            ->hourly()
            ->withoutOverlapping(60)
            ->name('importar-pasta-compartilhada');
        $schedule->job(new \App\Jobs\ReconciliarEnviosPendentesJob)
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->name('reconciliar-envios-pendentes');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Bearer Sanctum — sem statefulApi/CSRF (SPA em origem Vite distinta).
        $middleware->append(SecurityHeaders::class);
        // Nginx termina TLS; PHP-FPM vê HTTP. Sem isto HSTS e IP do rate limit erram.
        $middleware->trustProxies(at: '*');
        // API-only: sem rota nomeada `login`. Sem Accept JSON (PowerShell) não pode 500.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn ($request, \Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
