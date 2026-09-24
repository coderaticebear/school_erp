# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Laravel 12 (PHP 8.2+), PostgreSQL, run through Laravel Sail (Docker). The UI is server-rendered Blade built on the **AdminLTE 3** package (`jeroennoten/laravel-adminlte`) with Bootstrap. Auth scaffolding comes from `laravel/ui`. Tests use Pest 4.

**Styling uses Bootstrap / AdminLTE classes, not Tailwind.** Tailwind is listed in `package.json`, but `vite.config.js` only builds `resources/sass/app.scss` (Bootstrap). `resources/css/app.css` (Tailwind) isn't built. Ignore the Tailwind sections of the Boost guidelines below unless a page is explicitly moved to Tailwind.

## Commands

The app runs inside Sail containers (`compose.yaml`: `laravel.test`, `pgsql` on Postgres 18, `pgadmin` on port 5050). Put `./vendor/bin/sail` in front of `artisan` or `composer` commands so they reach the containerized database.

```bash
./vendor/bin/sail up -d                 # start containers (app on APP_PORT, default 80)
./vendor/bin/sail down
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan migrate:fresh --seed   # rebuild DB with demo data
./vendor/bin/sail npm run dev           # Vite (only used by layouts/app.blade.php, the auth pages)
./vendor/bin/sail psql

./vendor/bin/sail artisan test                          # full suite (composer test also clears config first)
./vendor/bin/sail artisan test --filter=SomeTest        # single test / file
./vendor/bin/sail pint                                  # format (Laravel Pint)
```

Tests run against a separate `testing` Postgres database (set in `phpunit.xml`; Sail creates it through the init SQL mounted in `compose.yaml`).

## Architecture

### Auth and roles
- The authenticatable model is **`App\Models\Login`** (table `login`), not `User`. `config/auth.php` defaults `AUTH_MODEL` to it. `User`/`users` is leftover Laravel scaffolding.
- `login.role` is an integer: **1 = admin, 2 = teacher, 3 = student, 4 = parent**. Use the `Login::ROLE_*` constants, not bare numbers. `Login` has `hasOne` relations to `Students`, `Teachers` and `Parents` through `login_id`. Only `is_active` accounts can log in, and public registration is disabled (admins create accounts).
- `routes/web.php` has one route group per role, guarded by `['auth', 'role:N']`. The `role` alias (`App\Http\Middleware\RoleMiddleware`) is registered in `bootstrap/app.php`. `/dashboard` redirects to the right role dashboard.
- `RoleMiddleware` returns 403 for a wrong role. Admin actions must live in the `role:1` group, even when they handle another entity (e.g. `POST /admin/students`).

### Sidebar navigation
The AdminLTE sidebar menu for each role is built at runtime in `App\Providers\EventServiceProvider`, which listens for AdminLTE's `BuildingMenu` event. It is not built in `config/adminlte.php`, which only holds the static Logout item. **When you add a page, add its entry to `EventServiceProvider::menuFor()`.** A test checks that every menu link resolves to a route and opens for its role, so don't link to pages that don't exist yet. (`MenuServiceProvider` is an empty stub.)

### Views
Views use `@extends('adminlte::page')` with `title`, `content_header` and `content` sections. They are grouped by role/entity under `resources/views/{admin,teacher,student,parent,subject}`. Overridden AdminLTE vendor views are in `resources/views/vendor/adminlte`.

### Domain model (academic structure)
Model classes use **plural names** (`Students`, `Teachers`, `Parents`, `Subjects`, `Classes`, `Divisions`), except `StudentClass` and `AcademicYear`.
- `Classes` (grade) → has many `Divisions` (sections, FK `class_id`).
- `StudentClass` (`student_classes`) assigns a student to a division for an academic year (`student_id`, `class_division_id`, `academic_year_id`).
- `AcademicYear` uses `is_active`. Code gets the current year with `AcademicYear::where('is_active', true)->first()` (see `AdminController::getCurrentAcademicYear`).
- `Students` belongs to `Parents` (`parent_id`).
- `Teachers` ↔ `Subjects` is many-to-many through `subject_teacher`.
- `Teachers` ↔ `Divisions` is many-to-many through `teacher_division`. Its `class_teacher` pivot flag is limited to one per division by a partial unique index. Clear the flag before re-syncing (see `DivisionTeacherController`).
- The database enforces uniqueness: case-insensitive `lower()` indexes on subject, class and division names (validate with `App\Rules\UniqueCaseInsensitive`), one active academic year, and one enrolment per student per year.

### Admin screens (Phase 1)
Controllers for setup data are in `app/Http/Controllers/Admin` (academic years, classes/divisions, division teachers). Students, teachers and subjects keep their controllers at the top level. All admin routes are named `admin.*`. Admin Form Requests extend `AdminFormRequest`, which handles admin authorization and sanitization. Flash messages and validation errors render through `@include('partials.alerts')`.

### Timetable (Phase 2)
- `periods` is the bell schedule. `is_break` rows (Lunch) show in grids but never get lessons. `config/school.php` holds the school days (`SCHOOL_DAYS`, ISO 1–7) and generator settings.
- `timetable_entries` has one row per (academic year, division, day, period). Two unique indexes forbid a double-booked division **and** a double-booked teacher.
- `App\Services\TimetableGenerator` builds timetables:
  - Only uses active teachers assigned to the division, and keeps one teacher per subject per division.
  - Caps a subject per day, and fills weekly targets from `subjects.periods_per_week`. Subjects with no value share the week evenly.
  - Fills slots across all divisions together, then repairs gaps by swapping lessons.
  - Runs `generate()` with a seed (deterministic in tests). `save()` replaces only the targeted divisions.
- `App\Services\TimetableGrid` builds day×period grids for a division or teacher. They render through `resources/views/timetable/grid.blade.php` (+ `timetable/styles.blade.php`, which includes the print CSS). The portals reuse the same partial.
- Manual slot edits go through `TimetableEntryRequest`. The teacher must be active, assigned to the division, teach the subject, and be free in that slot.
- `academic_year.timetable_published_at` controls whether teachers, students and parents can see the timetable.

### Teacher portal and attendance (Phase 3)
- Teacher pages are in `app/Http/Controllers/Teacher` under `/teacher/*` (routes named `teacher.*`). Get the profile with `$request->user()->teacherProfile()`, which returns 403 when a teacher login has no `teachers` row.
- The `teach-division` Gate (in `AppServiceProvider`) allows admins, or teachers assigned to that division. Use it for any division-scoped teacher action.
- The `attendance` table holds one row per student per date (unique), and a CHECK constraint limits `status` to `Attendance::STATUSES`. `Attendance::ATTENDED` (present and late) counts toward attendance percentages. `marked_by` points at the login that saved it.
- `DemoActivitySeeder` generates and publishes the timetable and adds two weeks of attendance, so every portal has demo data.

### Exams and results (Phase 4)
- `exams` belong to an academic year and have `max_marks`, `pass_marks` and `results_published_at`. `marks` has one row per (exam, student, subject) with either `marks` or `is_absent` (a CHECK enforces this). The upper bound comes from the exam and is validated in `SaveMarksRequest`.
- Marks entry (`MarksController`, `/marks/*`) is shared by admins and teachers. The `enter-marks` Gate allows admins, or a teacher assigned to the division who teaches the subject. Marks are locked while results are published. A blank row deletes a student's mark.
- `App\Services\ExamResults` calculates results:
  - An absent subject counts as 0 and fails the student. A missing subject makes the result `Incomplete`, which isn't ranked.
  - Ranks are competition ranks (1, 2, 2, 4).
  - Grades come from `config('school.grades')` via `Exam::gradeFor()`, and anything below the pass mark is F.
- The report card view (`exams/report-card.blade.php`) is shared by the admin, student and parent pages.
- `AcademicYearFactory` makes **inactive** years with unique names. Use `->active()` when a test needs the current year.

### Student and parent portals (Phase 5)
- `Portal\StudentPortalController` (`/student/*`) and `Portal\ParentPortalController` (`/parent/*`, with child pages at `/parent/children/{student}/*`) share the `StudentRecords` trait and the `resources/views/portal/*` views. The trait's `portalRoutes()` supplies `$routePrefix`/`$routeParams`, so links stay in the current portal.
- The `view-student` Gate allows admins, the student themselves, or the student's parent. Every parent child route must authorize it.
- Portals show a timetable only once `timetable_published_at` is set, and only exams with `results_published_at`. An unpublished report card returns 404.
- `App\Services\StudentOverview` supplies enrolment, the attendance summary and records, published results, and today's lessons.

### Reports and hardening (Phase 6)
- `App\Services\SchoolReports` powers the admin dashboard and `Admin\ReportController` (attendance by division or student with a `school.low_attendance_percent` flag, exam results by division or subject, CSV export).
- `Model::preventLazyLoading()` is on outside production, so an N+1 query throws in dev and tests. Eager-load relations (including `class` whenever you use `Divisions::$label`).
- Logout is POST only (AdminLTE's user menu). `Login` uses `Notifiable` so password-reset emails send. `Login::$name` feeds the navbar.
- `tests/Feature/Auth/RouteProtectionTest.php` fails if a new route lacks `auth` plus a `role:` middleware. Add public routes to its list on purpose, never by accident.

### Input sanitization and validation
`App\Pipelines\SanitizeInput::run(array $data)` sends input through a Laravel Pipeline (`TrimStrings`, `StripTags`, `NormalizeSpaces`, `EmptyStringToNull` in `app/Pipelines/Sanitizers`). Validation goes in Form Requests (`app/Http/Requests`), which call `SanitizeInput` in `prepareForValidation()`. **Never sanitize password fields**, since that would change the password (see `StoreStudentRequest::$unsanitized`).

### Seeding and demo logins
`DatabaseSeeder` uses model factories in `database/factories` (for example `Login::factory()->admin()`). It creates a single active `2025-2026` academic year, then classes and divisions, teachers, parents and students. It then calls `LoginSeeder`, which creates demo accounts with full profiles. Their password is `password`: `admin@example.com`, `teacher@example.com`, `student@example.com` (enrolled), and `parent@example.com` (the demo student's parent).

### Tests
Pest feature tests use `RefreshDatabase` against the `testing` Postgres database. `actingAsRole(Login::ROLE_X)` in `tests/Pest.php` creates and logs in a user with that role, including their teacher, student or parent profile. Tests that depend on "today" pin the clock with `Carbon::setTestNow()`.

## PostgreSQL notes
Differences from MySQL that cause real bugs here:
- `LIKE` is case-sensitive. For user-facing search, use `whereLike($col, $value, caseSensitive: false)` or `ilike`, and compare emails with `lower()`.
- `GROUP BY` is strict: every selected non-aggregate column must be grouped. Keep this in mind for attendance and report queries.
- Inserting explicit `id` values does not advance the sequence, so the next insert fails with a duplicate key. Let IDs auto-generate (don't hard-code `random_int(1, 6)`-style IDs either). Look up real rows instead.
- DDL is transactional, so a failed migration rolls back completely.
- Use Boost `database-schema` / `database-query` to check real columns and foreign keys before writing queries or migrations.

## Docker file sync
This machine uses the Docker Desktop for Linux context (`desktop-linux`). Laravel's Sail docs recommend `docker context use default` there. With the Desktop context, the container sometimes sees a stale copy of a file that was just replaced, as an editor's atomic save does. Before trusting a test run right after edits, confirm the container matches the host (e.g. `diff <(cat FILE) <(vendor/bin/sail exec -T laravel.test cat FILE)`).

## Environment
`.env` must set `PG_ADMIN_USERNAME` / `PG_ADMIN_PASSWORD` for the pgAdmin container, as well as the `DB_*` values (`DB_HOST=pgsql` under Sail).

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test`, simply run `vendor/bin/sail bin pint` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `vendor/bin/sail artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `vendor/bin/sail artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/sail bin pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `vendor/bin/sail artisan test --compact`.

</laravel-boost-guidelines>
