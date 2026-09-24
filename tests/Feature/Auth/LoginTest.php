<?php

use App\Models\Login;

test('an active user can log in and is sent to their dashboard', function (int $role, string $dashboard) {
    $login = Login::factory()->create(['role' => $role, 'password' => bcrypt('secret-pass')]);

    $this->post('/login', ['email' => $login->email, 'password' => 'secret-pass'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($login);
    $this->get('/dashboard')->assertRedirect($dashboard);
})->with([
    'admin' => [Login::ROLE_ADMIN, '/admin/dashboard'],
    'teacher' => [Login::ROLE_TEACHER, '/teacher/dashboard'],
    'student' => [Login::ROLE_STUDENT, '/student/dashboard'],
    'parent' => [Login::ROLE_PARENT, '/parent/dashboard'],
]);

test('a wrong password is rejected', function () {
    $login = Login::factory()->create(['password' => bcrypt('secret-pass')]);

    $this->post('/login', ['email' => $login->email, 'password' => 'nope'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an inactive account cannot log in', function () {
    $login = Login::factory()->inactive()->create(['password' => bcrypt('secret-pass')]);

    $this->post('/login', ['email' => $login->email, 'password' => 'secret-pass'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('remember me works on the login table', function () {
    $login = Login::factory()->create(['password' => bcrypt('secret-pass')]);

    $this->post('/login', ['email' => $login->email, 'password' => 'secret-pass', 'remember' => 'on'])
        ->assertRedirect('/dashboard');

    expect($login->fresh()->remember_token)->not->toBeNull();
});

test('a user can log out with a POST, and GET logout is not allowed', function () {
    actingAsRole(Login::ROLE_ADMIN);

    $this->get('/logout')->assertMethodNotAllowed();
    $this->assertAuthenticated();

    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

test('the navbar shows the user\'s name with a POST logout form', function () {
    $login = actingAsRole(Login::ROLE_TEACHER);

    $this->get('/teacher/dashboard')
        ->assertSee($login->teacher->full_name)
        ->assertSee('id="logout-form"', false);
});

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});
