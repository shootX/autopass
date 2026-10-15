<?php

use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrustConfiguredProxies;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tymon\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/dashboard.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(TrustProxies::class, TrustConfiguredProxies::class);
        $middleware->api(append: [SetApiLocale::class]);
        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/payment/return',
            '/callback/flitt/payment',
            '/callback/tbc/payment',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof HttpExceptionInterface
                || $e instanceof JWTException) {
                if ($e instanceof JWTException) {
                    return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
                }

                return null;
            }

            Log::warning('request_failed', [
                'path' => $request->path(),
                'exception' => $e::class,
            ]);

            return response()->json(['success' => false, 'error' => 'Request failed'], 500);
        });
    })->create();
