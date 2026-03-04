<?php

use App\Http\Middleware\RequestIdMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add Request ID to all requests
        $middleware->append(RequestIdMiddleware::class);

        // Configure Sanctum stateful middleware for API routes
        $middleware->statefulApi();

        // Register middleware aliases
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render validation errors in standard API format
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => 'VALIDATION_ERROR',
                    'errors' => $e->errors(),
                    'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
                ], 422);
            }
        });

        // Handle 404 errors for API routes
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'error_code' => 'NOT_FOUND',
                    'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
                ], 404);
            }
        });

        // Handle rate limiting errors for API routes
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please wait before trying again.',
                    'error_code' => 'RATE_LIMITED',
                    'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
                ], 429);
            }
        });

        // Handle unauthenticated errors for API routes
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error_code' => 'UNAUTHENTICATED',
                    'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
                ], 401);
            }
        });
    })->create();
