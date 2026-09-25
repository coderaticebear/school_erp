<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Allow the request through only when the logged-in user has one of the given roles.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        // Deactivation takes effect immediately, including existing sessions and remember-me cookies.
        if (! Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated. Please contact the office.']);
        }

        if ($roles && ! in_array((string) Auth::user()->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
