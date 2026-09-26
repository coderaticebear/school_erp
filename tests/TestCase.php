<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

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
