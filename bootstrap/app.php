<?php

use App\Http\Middleware\EnsureMenuAccess;
use App\Http\Middleware\RequireTwoFactor;
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
        $middleware->alias([
            'menu.access' => EnsureMenuAccess::class,
            'two-factor' => RequireTwoFactor::class,
        ]);

        // Unauthenticated portal requests go to the portal login, not /login.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('portal', 'portal/*')
            ? route('portal.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
