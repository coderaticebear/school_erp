<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Base for seeders that create demo data and demo accounts whose password is "password" (SEC-07).
 *
 * They refuse to run in production, whichever way they are started (db:seed, migrate --seed, or
 * another seeder's call()), and fail loudly so a deploy that seeds by mistake stops.
 */
abstract class DemoSeeder extends Seeder
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __invoke(array $parameters = []): mixed
    {
        if (app()->isProduction()) {
            throw new RuntimeException(sprintf(
                '%s creates demo accounts with the password "password" and never runs in production. '
                .'Create the first admin with: php artisan create-admin',
                class_basename(static::class),
            ));
        }

        return parent::__invoke($parameters);
    }
}
