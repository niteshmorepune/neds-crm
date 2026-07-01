<?php

use App\Http\Controllers\Api\BiometricWebhookController;
use App\Http\Middleware\EnsureMenuAccess;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\VerifyBiometricDeviceSerial;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // eSSL biometric device ADMS push. The ADMS protocol is hardcoded
            // to /iclock/cdata with NO configurable path (only server address
            // and port are settable on the device), so this must live outside
            // the api.php file's /api prefix.
            //   Server Address: crm.talktonitesh.com, Port: 443, HTTPS: ON
            //   Auth: SN query param validated against BIOMETRIC_DEVICE_SERIAL.
            // GET = device ping/registration handshake; POST = attendance log push.
            Route::middleware(['throttle:300,1', VerifyBiometricDeviceSerial::class])
                ->prefix('iclock')
                ->group(function (): void {
                    Route::get('/cdata', [BiometricWebhookController::class, 'ping'])
                        ->name('biometric.ping');
                    Route::post('/cdata', [BiometricWebhookController::class, 'push'])
                        ->name('biometric.push');
                });
        },
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
