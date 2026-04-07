<?php

use App\Http\Middleware\EnsureIntegrationToken;
use App\Http\Middleware\EnsureManager;
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
        $middleware->redirectUsersTo('/');
        $middleware->validateCsrfTokens(except: [
            '1c/exchange',
            '1c_exchange.php',
        ]);
        $middleware->alias([
            'integration.token' => EnsureIntegrationToken::class,
            'manager' => EnsureManager::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
