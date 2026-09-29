<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class ProvisionDatabaseRoles extends Command
{
    use ConfirmableTrait;

    /**
     * @var string
     */
    protected $signature = 'db:provision-roles
                            {databases?* : Databases to set up (defaults to DB_DATABASE)}
                            {--force : Run without confirmation in production}';

    /**
     * @var string
     */
    protected $description = 'Create the schema-owner and app database roles; the app role gets row access only';

    /**
     * Role names are written into DDL, so they are limited to plain lowercase identifiers.
     */
    protected const ROLE_NAME = '/^[a-z_][a-z0-9_]{0,62}$/';

    /**
     * Set up both roles, hand every existing object to the owner, and grant the app role row access.
     */
    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $app = config('database.connections.pgsql');
        $owner = config('database.connections.pgsql_migrations');
        $admin = config('database.connections.pgsql_admin');

        if (($problem = $this->configurationProblem($app, $owner, $admin)) !== null) {
            $this->components->error($problem);

            return self::FAILURE;
        }

        if (blank($admin['password'])) {
            $password = $this->secret("Password for the database administrator \"{$admin['username']}\"");

            if (blank($password)) {
                $this->components->error('The administrator password is required (set DB_ADMIN_PASSWORD or enter it when asked).');

                return self::FAILURE;
            }

            config(['database.connections.pgsql_admin.password' => $password]);
        }

        $databases = $this->argument('databases') ?: [$app['database']];

        $this->components->task("Role {$owner['username']} (schema owner)", fn () => $this->upsertRole($owner['username'], $owner['password']));
        $this->components->task("Role {$app['username']} (app)", fn () => $this->upsertRole($app['username'], $app['password']));

        foreach ($databases as $database) {
            $this->components->task("Database {$database}", fn () => $this->provisionDatabase($database, $owner['username'], $app['username']));
        }

        return $this->verify($databases) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Explain what is wrong with the connection settings, or null when they are usable.
     *
     * @param  array<string, mixed>  $app
     * @param  array<string, mixed>  $owner
     * @param  array<string, mixed>  $admin
     */
    protected function configurationProblem(array $app, array $owner, array $admin): ?string
    {
        foreach (['DB_USERNAME' => $app['username'], 'DB_MIGRATION_USERNAME' => $owner['username']] as $setting => $name) {
            if (! preg_match(self::ROLE_NAME, (string) $name)) {
                return "{$setting} must be a lowercase role name (letters, digits and underscores).";
            }
        }

        if (blank($admin['username'])) {
            return 'DB_ADMIN_USERNAME is not set. The roles are created by a separate administrator account.';
        }

        if ($app['username'] === $owner['username']) {
            return 'DB_USERNAME and DB_MIGRATION_USERNAME must be different roles.';
        }

        if (in_array($admin['username'], [$app['username'], $owner['username']], true)) {
            return 'The administrator (DB_ADMIN_USERNAME) must not be the app or migration role.';
        }

        if (blank($app['password']) || blank($owner['password'])) {
            return 'DB_PASSWORD and DB_MIGRATION_PASSWORD must both be set.';
        }

        return null;
    }

    /**
     * Create the login role, or reset an existing one, with no server-level privileges.
     */
    protected function upsertRole(string $name, string $password): void
    {
        $admin = DB::connection('pgsql_admin');
        $verb = $admin->selectOne('select 1 from pg_roles where rolname = ?', [$name]) ? 'ALTER' : 'CREATE';

        $admin->statement(sprintf(
            '%s ROLE %s WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %s',
            $verb,
            $this->identifier($name),
            $admin->getPdo()->quote($password),
        ));
    }

    /**
     * Make the owner role own the database and every object in it, and give the app role row access only.
     */
    protected function provisionDatabase(string $database, string $owner, string $app): void
    {
        $connection = $this->connectionTo('pgsql_admin', $database);
        [$db, $o, $a] = [$this->identifier($database), $this->identifier($owner), $this->identifier($app)];

        $connection->statement("ALTER DATABASE {$db} OWNER TO {$o}");
        $connection->statement("REVOKE ALL ON DATABASE {$db} FROM PUBLIC");
        $connection->statement("GRANT CONNECT ON DATABASE {$db} TO {$a}");

        // Keep row values out of the server log: the DETAIL of a constraint error repeats the whole row.
        $connection->statement("ALTER DATABASE {$db} SET log_error_verbosity = 'terse'");

        // Sequences created by serial or identity columns follow their table, so they are skipped here.
        $relations = $connection->select(<<<'SQL'
            select c.relname as name, c.relkind as kind
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public'
              and c.relkind in ('r', 'p', 'v', 'm', 'S')
              and c.relowner <> (select oid from pg_roles where rolname = ?)
              and not exists (
                  select 1 from pg_depend d
                  where d.classid = 'pg_class'::regclass and d.objid = c.oid and d.deptype in ('a', 'i')
              )
            SQL, [$owner]);

        foreach ($relations as $relation) {
            $keyword = match ($relation->kind) {
                'v' => 'VIEW',
                'm' => 'MATERIALIZED VIEW',
                'S' => 'SEQUENCE',
                default => 'TABLE',
            };

            $connection->statement("ALTER {$keyword} public.{$this->identifier($relation->name)} OWNER TO {$o}");
        }

        $connection->statement('REVOKE CREATE ON SCHEMA public FROM PUBLIC');
        $connection->statement("GRANT USAGE, CREATE ON SCHEMA public TO {$o}");
        $connection->statement("REVOKE CREATE ON SCHEMA public FROM {$a}");
        $connection->statement("GRANT USAGE ON SCHEMA public TO {$a}");

        $connection->statement("REVOKE ALL ON ALL TABLES IN SCHEMA public FROM {$a}");
        $connection->statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$a}");
        $connection->statement("REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM {$a}");
        $connection->statement("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO {$a}");

        // Tables that future migrations create (as the owner) get the same row access automatically.
        $connection->statement("ALTER DEFAULT PRIVILEGES FOR ROLE {$o} IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$a}");
        $connection->statement("ALTER DEFAULT PRIVILEGES FOR ROLE {$o} IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO {$a}");

        DB::purge('provision_target');
    }

    /**
     * Sign in as each role and confirm what it can and cannot do.
     *
     * @param  list<string>  $databases
     */
    protected function verify(array $databases): bool
    {
        $rows = [];
        $passed = true;

        foreach ($databases as $database) {
            foreach (['pgsql' => false, 'pgsql_migrations' => true] as $connectionName => $mayChangeSchema) {
                $role = $this->connectionTo($connectionName, $database)->selectOne(<<<'SQL'
                    select current_user as name, r.rolsuper, r.rolcreaterole, r.rolcreatedb, r.rolbypassrls,
                           has_schema_privilege('public', 'CREATE') as can_change_schema,
                           current_setting('log_error_verbosity') as log_error_verbosity
                    from pg_roles r
                    where r.rolname = current_user
                    SQL);

                DB::purge('provision_target');

                $ok = ! $role->rolsuper && ! $role->rolcreaterole && ! $role->rolcreatedb && ! $role->rolbypassrls
                    && $role->can_change_schema === $mayChangeSchema
                    && $role->log_error_verbosity === 'terse';
                $passed = $passed && $ok;

                $rows[] = [
                    $database,
                    $role->name,
                    $role->rolsuper ? 'yes' : 'no',
                    $role->rolcreaterole || $role->rolcreatedb ? 'yes' : 'no',
                    $role->rolbypassrls ? 'yes' : 'no',
                    $role->can_change_schema ? 'yes' : 'no',
                    $role->log_error_verbosity,
                    $ok ? 'OK' : 'WRONG',
                ];
            }
        }

        $this->table(['Database', 'Role', 'Superuser', 'Create roles/DBs', 'Bypass RLS', 'Change schema', 'Error log detail', 'Check'], $rows);

        $passed
            ? $this->components->info('The app role can only read and write rows; only the owner role can change the schema; the server log leaves out row values.')
            : $this->components->error('A role has more (or fewer) privileges than it should. See the table above.');

        return $passed;
    }

    /**
     * A fresh connection that uses the given connection's credentials against another database.
     */
    protected function connectionTo(string $connectionName, string $database): Connection
    {
        DB::purge('provision_target');
        config(['database.connections.provision_target' => [...config("database.connections.{$connectionName}"), 'database' => $database, 'url' => null]]);

        return DB::connection('provision_target');
    }

    protected function identifier(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
}
