<?php

/**
 * Security SEC-05: pages load fonts, scripts and styles only from our own server, so a visit never
 * sends the visitor's IP address to a third party.
 */

use App\Models\Login;
use App\Providers\EventServiceProvider;
use Illuminate\Testing\TestResponse;

/**
 * Every script, stylesheet, image or frame URL in the page that points at another server.
 *
 * @return list<string>
 */
function outsideAssets(TestResponse $response): array
{
    preg_match_all('/<(?:script|link|img|iframe|source)\b[^>]*?\b(?:src|href)\s*=\s*["\']([^"\']+)["\']/i', $response->getContent(), $matches);
    $ownHost = parse_url(config('app.url'), PHP_URL_HOST);

    return array_values(array_filter($matches[1], function (string $url) use ($ownHost) {
        $host = parse_url(html_entity_decode($url), PHP_URL_HOST);

        return $host !== null && $host !== $ownHost;
    }));
}

test('the sign-in and password reset pages load nothing from another server', function (string $url) {
    expect(outsideAssets($this->get($url)->assertSuccessful()))->toBe([]);
})->with(['/login', '/password/reset']);

test('no page in a role\'s sidebar loads anything from another server', function (int $role) {
    actingAsRole($role);

    $outside = [];
    foreach (array_filter(EventServiceProvider::menuFor($role), fn ($item) => isset($item['url'])) as $item) {
        foreach (outsideAssets($this->get('/'.$item['url'])->assertSuccessful()) as $asset) {
            $outside[] = "{$item['url']} → {$asset}";
        }
    }

    expect($outside)->toBe([]);
})->with([
    'admin' => Login::ROLE_ADMIN,
    'teacher' => Login::ROLE_TEACHER,
    'student' => Login::ROLE_STUDENT,
    'parent' => Login::ROLE_PARENT,
]);

test('the table script loads only on the lists that use it', function () {
    actingAsRole(Login::ROLE_ADMIN);
    $script = asset('vendor/datatables/js/dataTables.min.js');

    $this->get('/students')->assertSee($script, false);
    $this->get('/teachers')->assertSee($script, false);
    $this->get('/admin/dashboard')->assertDontSee('vendor/datatables', false);
});
