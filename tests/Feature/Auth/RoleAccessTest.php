<?php

use App\Models\Login;
use App\Providers\EventServiceProvider;
use Illuminate\Support\Facades\Route;

$areas = [
    'admin' => [Login::ROLE_ADMIN, '/admin/dashboard'],
    'teacher' => [Login::ROLE_TEACHER, '/teacher/dashboard'],
    'student' => [Login::ROLE_STUDENT, '/student/dashboard'],
    'parent' => [Login::ROLE_PARENT, '/parent/dashboard'],
];

test('guests are redirected to login from every portal', function (int $role, string $url) {
    $this->get($url)->assertRedirect('/login');
})->with($areas);

test('each role can open its own dashboard', function (int $role, string $url) {
    actingAsRole($role);

    $this->get($url)->assertSuccessful();
})->with($areas);

test('other roles are forbidden from a portal', function (int $ownerRole, string $url) {
    foreach ([Login::ROLE_ADMIN, Login::ROLE_TEACHER, Login::ROLE_STUDENT, Login::ROLE_PARENT] as $role) {
        if ($role === $ownerRole) {
            continue;
        }

        actingAsRole($role);
        $this->get($url)->assertForbidden();
    }
})->with($areas);

test('non-admins cannot reach admin pages or actions', function (int $role) {
    actingAsRole($role);

    $this->get('/students')->assertForbidden();
    $this->get('/teachers')->assertForbidden();
    $this->get('/subjects')->assertForbidden();
    $this->get('/admin/addStudent')->assertForbidden();
    $this->get('/admin/timetable')->assertForbidden();
    $this->post('/admin/students', [])->assertForbidden();
    $this->post('/admin/getParentByEmail', ['email' => 'a@b.com'])->assertForbidden();
    $this->post('/admin/timetable/generate')->assertForbidden();
    $this->put('/admin/timetable/entries', [])->assertForbidden();
    $this->delete('/admin/timetable/entries', [])->assertForbidden();
    $this->post('/admin/timetable/publish')->assertForbidden();
    $this->get('/admin/timetable/export?division=1')->assertForbidden();
    $this->get('/admin/periods')->assertForbidden();

    $this->get('/admin/academic-years')->assertForbidden();
    $this->post('/admin/academic-years', ['year' => '2030-2031'])->assertForbidden();
    $this->get('/admin/classes')->assertForbidden();
    $this->post('/admin/classes', ['class_name' => 'X'])->assertForbidden();
    $this->post('/admin/divisions', [])->assertForbidden();
    $this->post('/admin/subjects', ['subject_name' => 'X'])->assertForbidden();
    $this->get('/admin/teachers/create')->assertForbidden();
    $this->post('/admin/teachers', [])->assertForbidden();
    $this->get('/teachers/1')->assertForbidden();
})->with([
    'teacher' => Login::ROLE_TEACHER,
    'student' => Login::ROLE_STUDENT,
    'parent' => Login::ROLE_PARENT,
]);

test('every sidebar link points at a registered route', function (int $role) {
    $paths = collect(Route::getRoutes())->map(fn ($route) => trim($route->uri(), '/'));

    foreach (array_filter(EventServiceProvider::menuFor($role), fn ($item) => isset($item['url'])) as $item) {
        expect($paths)->toContain(trim($item['url'], '/'));
    }
})->with([Login::ROLE_ADMIN, Login::ROLE_TEACHER, Login::ROLE_STUDENT, Login::ROLE_PARENT]);

test('each role can open every page in its own sidebar', function (int $role) {
    actingAsRole($role);

    foreach (array_filter(EventServiceProvider::menuFor($role), fn ($item) => isset($item['url'])) as $item) {
        $this->get('/'.$item['url'])->assertSuccessful();
    }
})->with([Login::ROLE_ADMIN, Login::ROLE_TEACHER, Login::ROLE_STUDENT, Login::ROLE_PARENT]);
