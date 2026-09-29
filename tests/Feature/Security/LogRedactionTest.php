<?php

/**
 * Security SEC-04: personal data (database rows, emails, reset links) never reaches the logs.
 */

use App\Logging\RedactPersonalData;
use App\Models\Login;
use App\Models\Subjects;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->logPath = tempnam(sys_get_temp_dir(), 'sec04-');
    config(['logging.default' => 'single', 'logging.channels.single.path' => $this->logPath]);
    Log::forgetChannel('single');
});

afterEach(fn () => File::delete($this->logPath));

/**
 * Run an insert that the database rejects, and report the error the way the app does.
 *
 * @param  array<string, mixed>  $row
 */
function reportRejectedInsert(string $table, array $row): void
{
    try {
        DB::transaction(fn () => DB::table($table)->insert($row));
    } catch (QueryException $exception) {
        report($exception);

        return;
    }

    throw new RuntimeException("The insert into {$table} should have been rejected.");
}

test('a rejected row is logged with the error but without the row', function () {
    reportRejectedInsert('parents', ['first_name' => 'Zelda', 'last_name' => 'Quartermaine', 'address_line_1' => '42 Wallaby Way']);

    expect(File::get($this->logPath))
        ->toContain('QueryException')
        ->toContain('null value in column "login_id" of relation "parents" violates not-null constraint')
        ->not->toContain('Zelda')
        ->not->toContain('Quartermaine')
        ->not->toContain('Wallaby');
});

test('a duplicate key is logged with the error but without the key value', function () {
    Subjects::factory()->create(['subject_name' => 'Astrobotany']);

    reportRejectedInsert('subjects', Subjects::factory()->raw(['subject_name' => 'Astrobotany']));

    expect(File::get($this->logPath))
        ->toContain('duplicate key value violates unique constraint')
        ->not->toContain('Astrobotany')
        ->not->toContain('astrobotany');
});

test('a password reset email written to the log hides the address and the reset link', function () {
    config(['mail.default' => 'log']);
    $login = Login::factory()->create(['email' => 'zelda.quartermaine@example.com']);

    $this->post('/password/email', ['email' => $login->email])->assertSessionHas('status');

    expect(File::get($this->logPath))
        ->toContain('/password/reset/')
        ->not->toContain('zelda.quartermaine')
        ->not->toMatch('#/password/reset/[0-9a-f]{64}#');
});

test('every log channel that writes somewhere masks personal data', function () {
    $unmasked = collect(config('logging.channels'))
        ->except(['stack', 'null', 'emergency'])
        ->reject(fn (array $channel) => in_array(RedactPersonalData::class, $channel['tap'] ?? [], true))
        ->keys();

    expect($unmasked->all())->toBe([]);
});
