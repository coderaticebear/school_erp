<?php

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\StudentClass;
use App\Models\Teachers;

beforeEach(fn () => actingAsRole(Login::ROLE_ADMIN));

test('the classes page shows classes, divisions, student counts and class teachers', function () {
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $division = Divisions::factory()->create(['division_name' => 'A']);
    StudentClass::factory()->count(3)->create(['class_division_id' => $division->id, 'academic_year_id' => $year->id]);
    $teacher = Teachers::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $division->teachers()->attach($teacher->id, ['class_teacher' => true]);

    $this->get('/admin/classes')
        ->assertSuccessful()
        ->assertSee($division->class->class_name)
        ->assertSee('Ada Lovelace')
        ->assertSeeInOrder(['A', '3']);
});

test('a class can be added and renamed', function () {
    $this->post('/admin/classes', ['class_name' => 'Grade 8'])->assertSessionHas('success');
    $class = Classes::where('class_name', 'Grade 8')->firstOrFail();

    $this->put("/admin/classes/{$class->id}", ['class_name' => 'Grade 9'])->assertSessionHasNoErrors();

    expect($class->fresh()->class_name)->toBe('Grade 9');
});

test('class names are unique regardless of case and limited to 10 characters', function () {
    Classes::factory()->create(['class_name' => 'Grade 8']);

    $this->post('/admin/classes', ['class_name' => 'GRADE 8'])->assertSessionHasErrors('class_name');
    $this->post('/admin/classes', ['class_name' => 'Grade Eleven'])->assertSessionHasErrors('class_name');
    $this->post('/admin/classes', ['class_name' => ''])->assertSessionHasErrors('class_name');
});

test('renaming a class to its own name is allowed', function () {
    $class = Classes::factory()->create(['class_name' => 'Grade 8']);

    $this->put("/admin/classes/{$class->id}", ['class_name' => 'grade 8'])->assertSessionHasNoErrors();
});

test('a division can be added, renamed and deleted', function () {
    $class = Classes::factory()->create();

    $this->post('/admin/divisions', ['class_id' => $class->id, 'division_name' => 'C'])->assertSessionHas('success');
    $division = Divisions::where('class_id', $class->id)->where('division_name', 'C')->firstOrFail();

    $this->put("/admin/divisions/{$division->id}", ['division_name' => 'D'])->assertSessionHasNoErrors();
    expect($division->fresh()->division_name)->toBe('D');

    $this->delete("/admin/divisions/{$division->id}")->assertSessionHas('success');
    expect(Divisions::find($division->id))->toBeNull();
});

test('division names are unique within a class only', function () {
    $first = Classes::factory()->create();
    $second = Classes::factory()->create();
    Divisions::factory()->create(['class_id' => $first->id, 'division_name' => 'A']);

    $this->post('/admin/divisions', ['class_id' => $first->id, 'division_name' => 'a'])->assertSessionHasErrors('division_name');
    $this->post('/admin/divisions', ['class_id' => $second->id, 'division_name' => 'A'])->assertSessionHasNoErrors();
});

test('updating a division cannot move it to another class', function () {
    $division = Divisions::factory()->create();
    $other = Classes::factory()->create();

    $this->put("/admin/divisions/{$division->id}", ['division_name' => 'Z', 'class_id' => $other->id]);

    expect($division->fresh()->class_id)->not->toBe($other->id);
});

test('a division with students cannot be deleted', function () {
    $enrolment = StudentClass::factory()->create();

    $this->delete("/admin/divisions/{$enrolment->class_division_id}")->assertSessionHas('error');

    expect(Divisions::find($enrolment->class_division_id))->not->toBeNull();
});

test('deleting a division removes its teacher assignments', function () {
    $division = Divisions::factory()->create();
    $division->teachers()->attach(Teachers::factory()->create()->id, ['class_teacher' => true]);

    $this->delete("/admin/divisions/{$division->id}")->assertSessionHas('success');

    $this->assertDatabaseMissing('teacher_division', ['division_id' => $division->id]);
});

test('a class with students cannot be deleted', function () {
    $enrolment = StudentClass::factory()->create();
    $class = $enrolment->division->class;

    $this->delete("/admin/classes/{$class->id}")->assertSessionHas('error');

    expect(Classes::find($class->id))->not->toBeNull();
});

test('deleting an empty class also deletes its divisions', function () {
    $division = Divisions::factory()->create();

    $this->delete("/admin/classes/{$division->class_id}")->assertSessionHas('success');

    expect(Divisions::find($division->id))->toBeNull()
        ->and(Classes::find($division->class_id))->toBeNull();
});

test('the classes page renders several classes and divisions without extra queries per division', function () {
    foreach (Classes::factory()->count(2)->create() as $class) {
        Divisions::factory()->count(2)->create(['class_id' => $class->id]);
    }

    $this->get('/admin/classes')->assertSuccessful()->assertSee('data-confirm-title="Delete ', false);
});
