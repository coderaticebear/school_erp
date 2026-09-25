<?php

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Mark;
use App\Models\StudentClass;
use App\Models\Subjects;
use App\Models\Teachers;

beforeEach(function () {
    $this->year = AcademicYear::factory()->create(['is_active' => true]);
    actingAsRole(Login::ROLE_ADMIN);
});

test('the exams page lists the active year\'s exams', function () {
    Exam::factory()->create(['name' => 'Term 1 Midterm']);
    Exam::factory()->create(['name' => 'Old Exam', 'academic_year_id' => AcademicYear::factory()->create(['year' => '2020-2021'])->id]);

    $this->get('/admin/exams')->assertSuccessful()->assertSee('Term 1 Midterm')->assertDontSee('Old Exam');
});

test('an exam can be added for the active year and edited', function () {
    $this->post('/admin/exams', ['name' => 'Finals', 'starts_on' => '2026-03-01', 'max_marks' => 50, 'pass_marks' => 20])
        ->assertSessionHasNoErrors();
    $exam = Exam::where('name', 'Finals')->firstOrFail();
    expect($exam->academic_year_id)->toBe($this->year->id)->and($exam->max_marks)->toBe(50);

    $this->put("/admin/exams/{$exam->id}", ['name' => 'Final Exams', 'starts_on' => '', 'max_marks' => 60, 'pass_marks' => 25])->assertSessionHasNoErrors();
    expect($exam->fresh()->name)->toBe('Final Exams')->and($exam->fresh()->starts_on)->toBeNull();
});

test('exam validation', function (array $payload, string $field) {
    Exam::factory()->create(['name' => 'Finals']);

    $this->post('/admin/exams', [...['name' => 'New', 'max_marks' => 100, 'pass_marks' => 40], ...$payload])->assertSessionHasErrors($field);
})->with([
    'pass above max' => [['pass_marks' => 120], 'pass_marks'],
    'zero max' => [['max_marks' => 0], 'max_marks'],
    'duplicate name any case' => [['name' => 'FINALS'], 'name'],
    'missing name' => [['name' => ''], 'name'],
]);

test('the same exam name is allowed in a different academic year', function () {
    Exam::factory()->create(['name' => 'Finals', 'academic_year_id' => AcademicYear::factory()->create(['year' => '2020-2021'])->id]);

    $this->post('/admin/exams', ['name' => 'Finals', 'max_marks' => 100, 'pass_marks' => 40])->assertSessionHasNoErrors();
});

test('max marks cannot drop below a mark already entered', function () {
    $mark = Mark::factory()->create(['marks' => 80]);

    $this->put("/admin/exams/{$mark->exam_id}", ['name' => 'x', 'max_marks' => 50, 'pass_marks' => 20])->assertSessionHas('error');
    expect($mark->exam->fresh()->max_marks)->toBe(100);
});

test('an exam with marks cannot be deleted, an empty one can', function () {
    $used = Mark::factory()->create()->exam;
    $empty = Exam::factory()->create();

    $this->delete("/admin/exams/{$used->id}")->assertSessionHas('error');
    $this->delete("/admin/exams/{$empty->id}")->assertRedirect(route('admin.exams.index'));

    expect(Exam::find($used->id))->not->toBeNull()->and(Exam::find($empty->id))->toBeNull();
});

test('the exam page shows marks progress per division and subject', function () {
    $exam = Exam::factory()->create();
    $division = Divisions::factory()->create();
    $subject = Subjects::factory()->create(['subject_name' => 'Botany']);
    $teacher = Teachers::factory()->create();
    $teacher->subjects()->attach($subject->id);
    $division->teachers()->attach($teacher->id, ['class_teacher' => false]);
    $enrolments = StudentClass::factory()->count(2)->create(['class_division_id' => $division->id]);
    Mark::factory()->create(['exam_id' => $exam->id, 'student_id' => $enrolments[0]->student_id, 'subject_id' => $subject->id]);

    $this->get("/admin/exams/{$exam->id}")->assertSuccessful()->assertSee($division->label)->assertSee('Botany')->assertSee('1/2');
});

test('results can be published and unpublished', function () {
    $exam = Exam::factory()->create();

    $this->post("/admin/exams/{$exam->id}/publish")->assertSessionHas('success');
    expect($exam->fresh()->isPublished())->toBeTrue();

    $this->post("/admin/exams/{$exam->id}/publish");
    expect($exam->fresh()->isPublished())->toBeFalse();
});

test('non-admins cannot manage exams', function (int $role) {
    actingAsRole($role);
    $exam = Exam::factory()->create();

    $this->get('/admin/exams')->assertForbidden();
    $this->post('/admin/exams', ['name' => 'x', 'max_marks' => 10, 'pass_marks' => 5])->assertForbidden();
    $this->post("/admin/exams/{$exam->id}/publish")->assertForbidden();
})->with(['teacher' => Login::ROLE_TEACHER, 'student' => Login::ROLE_STUDENT, 'parent' => Login::ROLE_PARENT]);
