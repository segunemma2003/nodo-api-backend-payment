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
        // Ensure API routes return JSON errors instead of redirects
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                $statusCode = method_exists($e, 'getStatusCode') 
                    ? $e->getStatusCode() 
                    : ($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
                
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'An error occurred',
                    'error' => config('app.debug') ? [
                        'type' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ] : null,
                ], $statusCode);
            }
        });
    })->create();
