<?php

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Mark;
use App\Models\StudentClass;
use App\Models\Students;
use App\Models\Subjects;

beforeEach(function () {
    $this->year = AcademicYear::factory()->active()->create();
    $this->login = actingAsRole(Login::ROLE_PARENT);
    $this->parent = $this->login->parent;
    $this->division = Divisions::factory()->create();
    $this->kids = Students::factory()->count(2)->sequence(['first_name' => 'Maya'], ['first_name' => 'Leo'])->create(['parent_id' => $this->parent->id]);
    $this->kids->each(fn ($kid) => StudentClass::factory()->create(['student_id' => $kid->id, 'class_division_id' => $this->division->id]));
    $this->stranger = StudentClass::factory()->create()->student;
});

test('the dashboard lists each child with class, attendance and latest result', function () {
    Attendance::factory()->create(['student_id' => $this->kids[0]->id, 'division_id' => $this->division->id, 'status' => Attendance::ABSENT]);
    $exam = Exam::factory()->published()->create(['name' => 'Unit Test']);
    Mark::factory()->create(['exam_id' => $exam->id, 'student_id' => $this->kids[1]->id, 'subject_id' => Subjects::factory(), 'division_id' => $this->division->id, 'marks' => 95]);

    $this->get('/parent/dashboard')
        ->assertSuccessful()
        ->assertSee('Welcome, '.$this->parent->first_name)
        ->assertSee('Maya')
        ->assertSee('Leo')
        ->assertSee($this->division->label)
        ->assertSee('0%')
        ->assertSeeInOrder(['Unit Test', '<p class="stat-value">A+</p>', 'Pass · 95.0%'], false)
        ->assertDontSee($this->stranger->first_name.' '.$this->stranger->last_name);
});

test('a parent can open each page for their own child', function (string $page) {
    $this->get("/parent/children/{$this->kids[0]->id}{$page}")->assertSuccessful()->assertSee('Maya')->assertSee('All Children');
})->with(['' => '', 'timetable' => '/timetable', 'attendance' => '/attendance', 'results' => '/results']);

test('a parent cannot see another family\'s child', function (string $page) {
    $this->get("/parent/children/{$this->stranger->id}{$page}")->assertForbidden();
})->with(['' => '', 'timetable' => '/timetable', 'attendance' => '/attendance', 'results' => '/results']);

test('a parent can open a published report card for their child but not a draft one', function () {
    $published = Exam::factory()->published()->create();
    $draft = Exam::factory()->create();

    $this->get("/parent/children/{$this->kids[0]->id}/results/{$published->id}")->assertSuccessful()->assertSee('Maya');
    $this->get("/parent/children/{$this->kids[0]->id}/results/{$draft->id}")->assertNotFound();
    $this->get("/parent/children/{$this->stranger->id}/results/{$published->id}")->assertForbidden();
});

test('portal links stay inside the parent portal', function () {
    $this->get("/parent/children/{$this->kids[0]->id}")
        ->assertSee(route('parent.children.attendance', $this->kids[0]))
        ->assertDontSee(route('student.attendance'));
});

test('a parent with no linked children is told so', function () {
    Students::query()->update(['parent_id' => Students::factory()->create()->parent_id]);

    $this->get('/parent/dashboard')->assertSuccessful()->assertSee('No children are linked');
});

test('a parent cannot use other portals', function () {
    $this->get('/student/dashboard')->assertForbidden();
    $this->get('/teacher/dashboard')->assertForbidden();
    $this->get('/students')->assertForbidden();
});
