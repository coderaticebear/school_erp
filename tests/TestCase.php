<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Passwords the faked breach service reports as leaked. Empty unless a test adds one.
     *
     * @var list<string>
     */
    protected array $breachedPasswords = [];

    /**
     * No test reaches an outside server. Saving a password asks Have I Been Pwned whether it has leaked
     * (Security SEC-14), so every test gets a fake of its range API that knows only $breachedPasswords.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake([
            'api.pwnedpasswords.com/range/*' => fn (Request $request) => Http::response(
                $this->breachedPasswordsInRange(Str::afterLast($request->url(), '/'))
            ),
        ]);
    }

    /**
     * The range API's reply: a "HASH-SUFFIX:COUNT" line for each breached password whose SHA-1 starts with $prefix.
     */
    private function breachedPasswordsInRange(string $prefix): string
    {
        return collect($this->breachedPasswords)
            ->map(fn (string $password) => strtoupper(sha1($password)))
            ->filter(fn (string $hash) => str_starts_with($hash, strtoupper($prefix)))
            ->map(fn (string $hash) => substr($hash, 5).':1000')
            ->implode("\n");
    }

    /**
     * Rebuild the schema as the owner role; the tests themselves run as the app role, which has row access only.
     *
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        $seeder = $this->seeder();

        return [
            '--database' => 'pgsql_migrations',
            '--drop-views' => $this->shouldDropViews(),
            '--drop-types' => $this->shouldDropTypes(),
            ...($seeder ? ['--seeder' => $seeder] : ['--seed' => $this->shouldSeed()]),
        ];
    }
}
