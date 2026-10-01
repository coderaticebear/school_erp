<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side protections on every response (SEC-10), registered as global middleware so error
 * pages get them too.
 *
 * Scripts run only from our own server or with this request's nonce: inline <script> blocks carry
 * nonce="{{ Vite::cspNonce() }}", and inline onclick/onchange attributes are not allowed (use the
 * data attributes in public/js/school.js). Inline styles stay allowed: they can't run code, and
 * older school iPads don't support the directive that would allow only style attributes.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $response->headers->add([
            'Content-Security-Policy' => $this->contentSecurityPolicy(Vite::cspNonce()),
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()',
        ]);

        // Pages hold student records; shared school computers must not keep them in the browser cache.
        $response->headers->set('Cache-Control', 'no-store, private');

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // PHP adds this itself (unless expose_php=Off); it tells attackers the exact version.
        header_remove('X-Powered-By');

        return $response;
    }

    protected function contentSecurityPolicy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
