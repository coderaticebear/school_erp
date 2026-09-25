<?php

/**
 * Structural accessibility that every page relies on (skip link, landmarks, labelled fields).
 */

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\Teachers;

test('app pages have a skip link, one main landmark and a labelled sidebar', function (int $role, string $url) {
    AcademicYear::factory()->active()->create();
    actingAsRole($role);

    $html = $this->get($url)->assertSuccessful()->getContent();

    expect($html)->toContain('<a class="skip-link" href="#main-content">Skip to main content</a>')
        ->and(substr_count($html, '<main id="main-content"'))->toBe(1)
        ->and($html)->toContain('<nav class="pt-2" aria-label="Main">')
        ->and($html)->not->toContain('role="menu"')
        ->and($html)->toContain('css/school-theme.css');
})->with([
    'admin' => [Login::ROLE_ADMIN, '/admin/dashboard'],
    'teacher' => [Login::ROLE_TEACHER, '/teacher/dashboard'],
    'student' => [Login::ROLE_STUDENT, '/student/dashboard'],
    'parent' => [Login::ROLE_PARENT, '/parent/dashboard'],
]);

test('the login page has a main landmark and a level-one heading', function () {
    $html = $this->get('/login')->getContent();

    expect($html)->toContain('<main id="main-content"')->and($html)->toMatch('/<h1 class="login-logo">/');
});

test('form labels are linked to their fields', function () {
    AcademicYear::factory()->active()->create();
    actingAsRole(Login::ROLE_ADMIN);

    $html = $this->get('/admin/addStudent')->getContent();

    expect($html)->toContain('<label for="first-name">First Name</label>')
        ->and($html)->toContain('id="first-name"')
        ->and($html)->not->toMatch('/<label>\s*[A-Z]/');
});

test('the class teacher is marked with text, not colour alone', function () {
    actingAsRole(Login::ROLE_ADMIN);
    $teacher = Teachers::factory()->create();
    Divisions::factory()->create()->teachers()->attach($teacher->id, ['class_teacher' => true]);

    $this->get('/teachers')->assertSee('<span class="sr-only">(Class Teacher)</span>', false);
});
