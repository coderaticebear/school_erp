<?php

use App\Models\Subjects;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the app connects as its own role with no server privileges', function () {
    $role = DB::selectOne(<<<'SQL'
        select current_user as name, rolsuper, rolcreaterole, rolcreatedb, rolbypassrls, rolreplication,
               has_schema_privilege('public', 'CREATE') as can_change_schema
        from pg_roles where rolname = current_user
        SQL);

    expect($role->name)->toBe(config('database.connections.pgsql.username'))
        ->not->toBe(config('database.connections.pgsql_admin.username'))
        ->not->toBe(config('database.connections.pgsql_migrations.username'))
        ->and($role->rolsuper)->toBeFalse()
        ->and($role->rolcreaterole)->toBeFalse()
        ->and($role->rolcreatedb)->toBeFalse()
        ->and($role->rolbypassrls)->toBeFalse()
        ->and($role->rolreplication)->toBeFalse()
        ->and($role->can_change_schema)->toBeFalse();
});

test('the app role is refused anything beyond reading and writing rows', function (string $sql) {
    expect(fn () => DB::statement($sql))->toThrow(function (QueryException $exception) {
        expect($exception->getCode())->toBe('42501');
    });
})->with([
    'create a table' => 'create table probe (id int)',
    'drop a table' => 'drop table students',
    'alter a table' => 'alter table students add column probe int',
    'truncate a table' => 'truncate students',
    'create a role' => "create role probe login password 'probe-password'",
    'make itself a superuser' => 'alter role current_user superuser',
    'run a shell command' => "copy (select 1) to program 'id'",
    'read password hashes' => 'select rolpassword from pg_authid',
]);

test('the app role can read, write and delete rows', function () {
    $subject = Subjects::factory()->create(['subject_name' => 'Role Probe']);

    $subject->update(['subject_name' => 'Role Probe Renamed']);
    expect(Subjects::find($subject->id)->subject_name)->toBe('Role Probe Renamed');

    $subject->delete();
    expect(Subjects::find($subject->id))->toBeNull();
});
