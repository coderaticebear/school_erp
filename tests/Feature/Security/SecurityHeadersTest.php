<?php

/**
 * Security SEC-10: browser-side protections on every response, and pages that work under them.
 */

use App\Models\Login;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;

function nonceOf(TestResponse $response): string
{
    preg_match("/script-src 'self' 'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);

    return $match[1] ?? '';
}

test('every response carries the security headers', function (?int $role, string $url, int $status) {
    if ($role) {
        actingAsRole($role);
    }

    $response = $this->get($url)->assertStatus($status);

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]{20,}'(;|$)/");
    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin')
        ->assertHeader('Cache-Control', 'no-store, private');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()')->toContain('microphone=()')->toContain('geolocation=()');
    $response->assertHeaderMissing('Strict-Transport-Security');
})->with([
    'the sign-in page' => [null, '/login', 200],
    'a signed-in page' => [Login::ROLE_ADMIN, '/admin/dashboard', 200],
    'a page that does not exist' => [null, '/no-such-page', 404],
]);

test('each response gets a new script nonce', function () {
    $first = nonceOf($this->get('/login'));
    $second = nonceOf($this->get('/login'));

    expect($first)->not->toBe('')->not->toBe($second);
});

test('inline scripts carry the nonce of their own response', function () {
    actingAsRole(Login::ROLE_ADMIN);

    $response = $this->get('/students')->assertSuccessful();
    preg_match_all('/<script\b(?![^>]*\bsrc=)[^>]*>/i', $response->getContent(), $inlineScripts);

    expect($inlineScripts[0])->not->toBeEmpty()
        ->each->toContain('nonce="'.nonceOf($response).'"');
});

test('no view has an inline event handler or an inline script without a nonce', function () {
    $problems = [];

    foreach (File::allFiles(resource_path('views')) as $view) {
        $html = $view->getContents();
        $name = $view->getRelativePathname();

        if (preg_match('/<[a-z][^>]*\son[a-z]+\s*=/i', $html)) {
            $problems[] = "{$name}: inline on…= handler (use a data attribute from public/js/school.js)";
        }

        if (preg_match('/<script\b(?![^>]*\b(?:src|nonce)=)[^>]*>/i', $html)) {
            $problems[] = "{$name}: <script> without nonce=\"{{ Vite::cspNonce() }}\"";
        }
    }

    expect($problems)->toBe([]);
});

test('production also tells browsers to use HTTPS only', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->get('/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});
