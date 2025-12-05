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
        // Handle validation exceptions for API routes
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Handle all other exceptions for API routes
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                // Don't expose sensitive errors in production
                $message = $e->getMessage() ?: 'An error occurred';
                
                // For 404 errors
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Resource not found',
                    ], 404);
                }

                // For authentication/authorization errors
                if ($e instanceof \Illuminate\Auth\AuthenticationException || 
                    $e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 401);
                }

                // For model not found
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Resource not found',
                    ], 404);
                }

                $statusCode = method_exists($e, 'getStatusCode') 
                    ? $e->getStatusCode() 
                    : ($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
                
                return response()->json([
                    'success' => false,
                    'message' => $message,
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
