<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorAuth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two-factor gate (Security SEC-06). A signed-in user must set up two-factor first, and then enter a code
 * once per session, before they can open any page.
 */
class EnsureTwoFactor
{
    /**
     * Session key: the login id that passed the two-factor challenge in this session.
     */
    public const SESSION_VERIFIED = 'two_factor_verified_login_id';

    /**
     * Session key: the time (Unix timestamp) when the gate first sent this session to the challenge.
     */
    public const SESSION_STARTED = 'two_factor_challenge_started_at';

    /**
     * Send a signed-in user to two-factor set-up or the challenge until they pass it.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! app(TwoFactorAuth::class)->required() || $request->route() === null) {
            return $next($request);
        }

        /** @var \App\Models\Login $login */
        $login = Auth::user();

        if (! $login->is_active || $this->isOpenRoute($request)) {
            return $next($request);
        }

        if (! $login->hasTwoFactorEnabled()) {
            return redirect()->guest(route('two-factor.setup'));
        }

        $verifiedId = $request->session()->get(self::SESSION_VERIFIED);

        if (! is_numeric($verifiedId) || (int) $verifiedId !== (int) Auth::id()) {
            if (! $request->session()->has(self::SESSION_STARTED)) {
                $request->session()->put(self::SESSION_STARTED, now()->timestamp);
            }

            return redirect()->guest(route('two-factor.challenge'));
        }

        return $next($request);
    }

    /**
     * Routes a user who is not verified may still open: the two-factor pages and logout.
     */
    private function isOpenRoute(Request $request): bool
    {
        $name = (string) $request->route()->getName();

        return str_starts_with($name, 'two-factor.') || $name === 'logout';
    }
}
