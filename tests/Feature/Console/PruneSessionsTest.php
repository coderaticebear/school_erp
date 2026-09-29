<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, int>  $minutesAgo  session id => minutes since its last activity
 */
function insertSessions(array $minutesAgo): void
{
    foreach ($minutesAgo as $id => $minutes) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => null,
            'ip_address' => '203.0.113.7',
            'user_agent' => 'Test browser',
            'payload' => '',
            'last_activity' => now()->subMinutes($minutes)->getTimestamp(),
        ]);
    }
}

test('sessions:prune deletes sessions past their lifetime and keeps the rest', function () {
    config(['session.driver' => 'database', 'session.lifetime' => 120]);
    insertSessions(['expired' => 121, 'long-expired' => 60 * 24 * 30, 'active' => 5]);

    $this->artisan('sessions:prune')
        ->expectsOutputToContain('Deleted 2 expired sessions.')
        ->assertSuccessful();

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['active']);
});

test('sessions:prune runs every hour', function () {
    $prune = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'sessions:prune'));

    expect($prune?->expression)->toBe('0 * * * *');
});
