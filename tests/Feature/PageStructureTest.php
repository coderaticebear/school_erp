<?php

/**
 * Design review Step C: consistent page structure, navigation and form feedback.
 */

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\Parents;

beforeEach(fn () => AcademicYear::factory()->active()->create());

test('the admin sidebar is grouped and has no generic header', function () {
    actingAsRole(Login::ROLE_ADMIN);

    $this->get('/admin/dashboard')
        ->assertSeeInOrder(['People', 'Students', 'Teachers', 'Academics', 'Classes &amp; Divisions', 'Timetable', 'Bell Schedule', 'Exams', 'Marks Entry', 'Reports', 'Attendance Report'], false)
        ->assertDontSee('MAIN NAVIGATION');
});

test('pages use the shared header and no full-screen loading overlay', function () {
    actingAsRole(Login::ROLE_ADMIN);

    $this->get('/students')
        ->assertSee('<div class="page-header">', false)
        ->assertSee('<h1 class="mb-0">Students</h1>', false)
        ->assertDontSee('preloader');
});

test('add student is one page with every section visible', function () {
    actingAsRole(Login::ROLE_ADMIN);

    $this->get('/admin/addStudent')
        ->assertSeeInOrder(['Student', 'Student Login', 'Parent / Guardian', 'Address'])
        ->assertDontSee('role="tablist"', false)
        ->assertDontSee('id="next"', false)
        ->assertSee('<span class="text-danger" aria-hidden="true">*</span> Required', false);
});

test('validation errors are shown next to the field that caused them', function () {
    actingAsRole(Login::ROLE_ADMIN);

    $this->from('/admin/addStudent')->post('/admin/students', ['first_name' => '', 'email' => 'not-an-email'])
        ->assertRedirect('/admin/addStudent');

    $html = $this->get('/admin/addStudent')->getContent();

    expect($html)->toMatch('/name="first_name"[^>]*class="form-control is-invalid"/')
        ->and($html)->toContain('id="first-name-error"')
        ->and($html)->toContain('id="email-error"')
        ->and($html)->toContain('Please fix the fields marked below');
});

test('edit forms show inline errors too', function () {
    actingAsRole(Login::ROLE_ADMIN);
    $division = Divisions::factory()->create();
    $student = \App\Models\StudentClass::factory()->create(['class_division_id' => $division->id])->student;

    $this->from("/admin/students/{$student->id}/edit")->put("/admin/students/{$student->id}", ['first_name' => ''])
        ->assertRedirect("/admin/students/{$student->id}/edit");

    $this->get("/admin/students/{$student->id}/edit")->assertSee('id="first-name-error"', false);
});

test('the parent lookup returns the parent name for confirmation', function () {
    actingAsRole(Login::ROLE_ADMIN);
    $parent = Parents::factory()->create(['first_name' => 'Nora', 'last_name' => 'Finch']);

    $this->postJson('/admin/getParentByEmail', ['email' => $parent->login->email])
        ->assertJson(['parent_id' => $parent->id, 'name' => 'Nora Finch']);
});
