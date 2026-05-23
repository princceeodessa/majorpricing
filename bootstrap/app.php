<?php

use App\Http\Middleware\AuthenticateMobileToken;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureIntegrationToken;
use App\Http\Middleware\EnsureManager;
use App\Http\Middleware\NoIndexResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo('/catalog');
        $middleware->validateCsrfTokens(except: [
            '1c/exchange',
            '1c_exchange.php',
        ]);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'integration.token' => EnsureIntegrationToken::class,
            'manager' => EnsureManager::class,
            'mobile.auth' => AuthenticateMobileToken::class,
            'noindex' => NoIndexResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
