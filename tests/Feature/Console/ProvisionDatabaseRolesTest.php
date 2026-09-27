<?php

test('provisioning sets up both roles and passes its own privilege check', function () {
    $database = config('database.connections.pgsql.database');

    $this->artisan('db:provision-roles', ['databases' => [$database]])
        ->expectsOutputToContain('The app role can only read and write rows')
        ->doesntExpectOutputToContain('WRONG')
        ->assertSuccessful();
});

test('provisioning can run again without changing the outcome', function () {
    $database = config('database.connections.pgsql.database');

    $this->artisan('db:provision-roles', ['databases' => [$database]])->assertSuccessful();
    $this->artisan('db:provision-roles', ['databases' => [$database]])->assertSuccessful();
});

test('provisioning refuses unsafe role settings before touching the database', function (array $settings, string $message) {
    config($settings);

    $this->artisan('db:provision-roles')
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'app and owner are the same role' => [
        ['database.connections.pgsql_migrations.username' => 'school_app', 'database.connections.pgsql.username' => 'school_app'],
        'must be different roles',
    ],
    'app role is the administrator' => [
        ['database.connections.pgsql.username' => 'sail', 'database.connections.pgsql_admin.username' => 'sail'],
        'must not be the app or migration role',
    ],
    'role name is not a plain identifier' => [
        ['database.connections.pgsql.username' => 'School-App"; drop table students; --'],
        'must be a lowercase role name',
    ],
    'no migration password' => [
        ['database.connections.pgsql_migrations.password' => ''],
        'must both be set',
    ],
    'no administrator' => [
        ['database.connections.pgsql_admin.username' => null],
        'DB_ADMIN_USERNAME is not set',
    ],
]);

test('provisioning stops when no administrator password is given', function () {
    config(['database.connections.pgsql_admin.password' => null]);
    $admin = config('database.connections.pgsql_admin.username');

    $this->artisan('db:provision-roles')
        ->expectsQuestion("Password for the database administrator \"{$admin}\"", '')
        ->expectsOutputToContain('The administrator password is required')
        ->assertFailed();
});
