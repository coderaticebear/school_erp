<?php

use Illuminate\Support\Facades\Route;

/**
 * Guards against new routes being added without authentication or a role check.
 */
$public = ['/', 'login', 'logout', 'password/reset', 'password/email', 'password/reset/{token}', 'password/confirm', 'up'];

test('every non-public route requires login and a role', function () use ($public) {
    $unprotected = collect(Route::getRoutes())
        // Framework/vendor routes: debug tools, storage links, AdminLTE's session-only dark-mode toggle.
        ->filter(fn ($route) => ! str_starts_with($route->uri(), '_') && ! str_starts_with($route->uri(), 'storage') && ! str_starts_with($route->uri(), 'adminlte/'))
        ->reject(fn ($route) => in_array($route->uri(), $public, true))
        ->reject(fn ($route) => $route->uri() === 'dashboard') // auth-only; redirects by role
        ->filter(function ($route) {
            $middleware = $route->gatherMiddleware();

            return ! in_array('auth', $middleware, true)
                || ! collect($middleware)->contains(fn ($name) => is_string($name) && str_starts_with($name, 'role:'));
        })
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

test('guests are sent to login from every protected GET page', function () {
    collect(Route::getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true) && in_array('auth', $route->gatherMiddleware(), true))
        ->each(function ($route) {
            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $this->get('/'.$uri)->assertRedirect('/login');
        });
});
