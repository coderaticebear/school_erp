<?php

use App\Http\Middleware\RoleMiddleware;
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
        ]);

        // Check the role before route-model binding, so other roles get 403 (not 404) on admin URLs.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: RoleMiddleware::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Failed forms keep their input in the session; never keep password fields beyond Laravel's defaults.
        $exceptions->dontFlash(['parent_password']);
    })->create();
