<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asks for the password again (the `password.confirm` page) only for a CSV download (`?format=csv`).
 * The same page without `format=csv` opens with no password (SEC-06, FR-2FA-14).
 */
class ConfirmPasswordForCsv
{
    /**
     * The container builds RequirePassword with the `auth.password_timeout` setting (15 minutes).
     */
    public function __construct(protected RequirePassword $requirePassword) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query('format') !== 'csv') {
            return $next($request);
        }

        return $this->requirePassword->handle($request, $next);
    }
}
