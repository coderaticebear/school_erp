<?php

use App\Http\Middleware\ConfirmPasswordForCsv;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //

        $middleware->alias([
            'role' => RoleMiddleware::class,
            // Password again before a CSV download (SEC-06); other sensitive routes use `password.confirm`.
            'confirm-csv' => ConfirmPasswordForCsv::class,
        ]);

        // Security headers and the CSP nonce on every response, error pages included (SEC-10).
        $middleware->append(SecurityHeaders::class);

        // Two-factor gate (SEC-06): signed-in users set up two-factor and pass the challenge before any page.
        $middleware->web(append: [EnsureTwoFactor::class]);

        // Check the role before route-model binding, so other roles get 403 (not 404) on admin URLs.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: RoleMiddleware::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Failed forms keep their input in the session; never keep password fields beyond Laravel's defaults.
        // Two-factor codes (SEC-06) are secrets too.
        $exceptions->dontFlash(['parent_password', 'code', 'recovery_code']);
    })->create();
