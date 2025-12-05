<?php

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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\CorsMiddleware::class,
        ]);
        $middleware->web(prepend: [
            \App\Http\Middleware\CorsMiddleware::class,
        ]);
        $middleware->alias([
            'api.token' => \App\Http\Middleware\ApiTokenAuth::class,
            'business.auth' => \App\Http\Middleware\BusinessAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Prevent redirects on API routes - return JSON errors instead
        $exceptions->respond(function (\Illuminate\Http\Request $request, \Throwable $exception) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage() ?: 'An error occurred',
                    'error' => config('app.debug') ? [
                        'type' => get_class($exception),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ] : null,
                ], method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500);
            }
        });
    })->create();
