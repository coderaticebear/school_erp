<?php

/**
 * Security SEC-09: session payloads, which hold refilled form input, are encrypted in the sessions table.
 */

use App\Models\Login;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

test('a failed form\'s input is stored encrypted in the sessions table and still refills the form', function () {
    config(['session.driver' => 'database']);
    actingAsRole(Login::ROLE_ADMIN);

    $this->from('/admin/addStudent')
        ->post('/admin/students', ['first_name' => 'Zelda', 'last_name' => 'Quartermaine', 'email' => ''])
        ->assertRedirect('/admin/addStudent')
        ->assertSessionHasInput('last_name', 'Quartermaine');

    $payload = base64_decode(DB::table('sessions')->sole()->payload);

    expect($payload)->not->toContain('Quartermaine');
    expect(Crypt::decrypt($payload))->toContain('Quartermaine');
});
