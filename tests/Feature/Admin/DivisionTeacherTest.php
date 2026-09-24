<?php

use App\Models\Divisions;
use App\Models\Login;
use App\Models\Teachers;

beforeEach(function () {
    $this->division = Divisions::factory()->create();
    $this->teachers = Teachers::factory()->count(3)->create();
    actingAsRole(Login::ROLE_ADMIN);
});

test('the assignment page lists all teachers with current assignments checked', function () {
    $this->division->teachers()->attach($this->teachers[1]->id, ['class_teacher' => true]);

    $this->get("/admin/divisions/{$this->division->id}/teachers")
        ->assertSuccessful()
        ->assertSee($this->teachers[0]->full_name)
        ->assertSee('name="teacher_ids[]" value="'.$this->teachers[1]->id.'" class="teacher-toggle" checked', false);
});

test('admin can assign teachers and a class teacher', function () {
    $this->put("/admin/divisions/{$this->division->id}/teachers", [
        'teacher_ids' => [$this->teachers[0]->id, $this->teachers[1]->id],
        'class_teacher_id' => $this->teachers[1]->id,
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.classes.index'));

    $assigned = $this->division->teachers()->get();
    expect($assigned->pluck('id')->sort()->values()->all())->toBe([$this->teachers[0]->id, $this->teachers[1]->id])
        ->and($assigned->firstWhere('pivot.class_teacher', true)->id)->toBe($this->teachers[1]->id);
});

test('switching the class teacher works without breaking the one-per-division rule', function () {
    $this->division->teachers()->attach([
        $this->teachers[0]->id => ['class_teacher' => true],
        $this->teachers[1]->id => ['class_teacher' => false],
    ]);

    $this->put("/admin/divisions/{$this->division->id}/teachers", [
        'teacher_ids' => [$this->teachers[0]->id, $this->teachers[1]->id],
        'class_teacher_id' => $this->teachers[1]->id,
    ])->assertSessionHasNoErrors();

    expect($this->division->teachers()->wherePivot('class_teacher', true)->pluck('teachers.id')->all())->toBe([$this->teachers[1]->id]);
});

test('unticked teachers are removed and an empty list clears the division', function () {
    $this->division->teachers()->attach([$this->teachers[0]->id => ['class_teacher' => true], $this->teachers[2]->id => ['class_teacher' => false]]);

    $this->put("/admin/divisions/{$this->division->id}/teachers", ['teacher_ids' => [$this->teachers[2]->id]]);
    expect($this->division->teachers()->pluck('teachers.id')->all())->toBe([$this->teachers[2]->id])
        ->and($this->division->teachers()->wherePivot('class_teacher', true)->count())->toBe(0);

    $this->put("/admin/divisions/{$this->division->id}/teachers", []);
    expect($this->division->teachers()->count())->toBe(0);
});

test('the class teacher must be one of the assigned teachers', function () {
    $this->put("/admin/divisions/{$this->division->id}/teachers", [
        'teacher_ids' => [$this->teachers[0]->id],
        'class_teacher_id' => $this->teachers[2]->id,
    ])->assertSessionHasErrors('class_teacher_id');

    expect($this->division->teachers()->count())->toBe(0);
});

test('unknown teachers are rejected', function () {
    $this->put("/admin/divisions/{$this->division->id}/teachers", ['teacher_ids' => [999999]])
        ->assertSessionHasErrors('teacher_ids.0');
});

test('a teacher can be class teacher of one division and teach in others', function () {
    $other = Divisions::factory()->create();

    $this->put("/admin/divisions/{$this->division->id}/teachers", ['teacher_ids' => [$this->teachers[0]->id], 'class_teacher_id' => $this->teachers[0]->id]);
    $this->put("/admin/divisions/{$other->id}/teachers", ['teacher_ids' => [$this->teachers[0]->id]]);

    expect($this->teachers[0]->divisions()->count())->toBe(2);
});
