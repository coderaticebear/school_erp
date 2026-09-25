<?php

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\Parents;
use App\Models\StudentClass;
use App\Models\Students;

beforeEach(function () {
    $this->academicYear = AcademicYear::factory()->create(['year' => '2025-2026', 'is_active' => true]);
    $this->division = Divisions::factory()->create();
    actingAsRole(Login::ROLE_ADMIN);
});

function studentPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Asha',
        'last_name' => 'Varghese',
        'dob' => '2014-05-01',
        'gender' => 'female',
        'blood_group' => 'O+',
        'class_division_id' => test()->division->id,
        'address_line_1' => '12 Main St',
        'address_line_2' => '',
        'city' => 'Toronto',
        'province' => 'ON',
        'country' => 'Canada',
        'postal' => 'M1M1M1',
        'email' => 'asha@example.test',
        'password' => 'student-pass',
        'p_email' => 'mother@example.test',
        'parent_first_name' => 'Mary',
        'parent_last_name' => 'Varghese',
        'parent_area_code' => '416',
        'parent_phone' => '5551234',
        'parent_password' => 'parent-pass',
        ...$overrides,
    ];
}

test('the add student form lists divisions', function () {
    $this->get('/admin/addStudent')
        ->assertSuccessful()
        ->assertSee($this->division->division_name);
});

test('admin can add a student with a new parent and the student is enrolled', function () {
    $this->post('/admin/students', studentPayload())
        ->assertRedirect(route('admin.addStudent'))
        ->assertSessionHas('success');

    $student = Students::where('first_name', 'Asha')->firstOrFail();
    $parentLogin = Login::where('email', 'mother@example.test')->firstOrFail();

    expect($student->login->role)->toBe(Login::ROLE_STUDENT)
        ->and($student->login->email)->toBe('asha@example.test')
        ->and($parentLogin->role)->toBe(Login::ROLE_PARENT)
        ->and($student->parent->login_id)->toBe($parentLogin->id)
        ->and($student->parent->phone_number)->toBe('5551234')
        ->and($student->address_line_2)->toBeNull();

    $enrolment = StudentClass::where('student_id', $student->id)->firstOrFail();
    expect($enrolment->class_division_id)->toBe($this->division->id)
        ->and($enrolment->academic_year_id)->toBe($this->academicYear->id);
});

test('passwords are stored hashed and not sanitized', function () {
    $this->post('/admin/students', studentPayload(['password' => '  <b>pass word</b>  ']));

    $login = Login::where('email', 'asha@example.test')->firstOrFail();
    expect(Hash::check('  <b>pass word</b>  ', $login->password))->toBeTrue();
});

test('admin can add a student to an existing parent', function () {
    $parent = Parents::factory()->create();

    $this->post('/admin/students', studentPayload([
        'parent_id' => $parent->id,
        'p_email' => $parent->login->email,
        'parent_first_name' => '',
        'parent_password' => '',
    ]))->assertSessionHasNoErrors()->assertSessionHas('success');

    expect(Students::where('first_name', 'Asha')->firstOrFail()->parent_id)->toBe($parent->id)
        ->and(Parents::count())->toBe(1);
});

test('text input is sanitized before saving', function () {
    $this->post('/admin/students', studentPayload(['first_name' => '  <script>x</script>Asha  ', 'city' => 'New    York']));

    $student = Students::latest('id')->firstOrFail();
    expect($student->first_name)->toBe('xAsha')
        ->and($student->city)->toBe('New York');
});

test('validation errors are returned per field and input is kept', function (array $overrides, string $field) {
    $this->from('/admin/addStudent')
        ->post('/admin/students', studentPayload($overrides))
        ->assertRedirect('/admin/addStudent')
        ->assertSessionHasErrors($field)
        ->assertSessionHasInput('last_name');

    expect(Students::count())->toBe(0)->and(Login::count())->toBe(1);
})->with([
    'missing first name' => [['first_name' => ''], 'first_name'],
    'future birth date' => [['dob' => now()->addDay()->toDateString()], 'dob'],
    'bad blood group' => [['blood_group' => 'Z+'], 'blood_group'],
    'no division' => [['class_division_id' => ''], 'class_division_id'],
    'unknown division' => [['class_division_id' => 999999], 'class_division_id'],
    'short password' => [['password' => 'short'], 'password'],
    'new parent without name' => [['parent_first_name' => ''], 'parent_first_name'],
    'same email for parent and student' => [['p_email' => 'asha@example.test'], 'p_email'],
    'unknown parent id' => [['parent_id' => 999999], 'parent_id'],
]);

test('a student email that is already used is rejected', function () {
    Login::factory()->create(['email' => 'asha@example.test']);

    $this->post('/admin/students', studentPayload())->assertSessionHasErrors('email');
});

test('nothing is saved when there is no active academic year', function () {
    $this->academicYear->update(['is_active' => false]);

    $this->post('/admin/students', studentPayload())->assertSessionHas('error');

    expect(Students::count())->toBe(0)->and(Parents::count())->toBe(0);
});

test('parent lookup by email is case-insensitive and only finds parents', function () {
    $parent = Parents::factory()->create();
    $parent->login->update(['email' => 'Mixed.Case@Example.test']);
    $studentLogin = Login::factory()->student()->create(['email' => 'kid@example.test']);

    $this->postJson('/admin/getParentByEmail', ['email' => 'mixed.case@example.TEST'])
        ->assertJson(['parent_id' => $parent->id]);

    $this->postJson('/admin/getParentByEmail', ['email' => $studentLogin->email])
        ->assertJson(['parent_id' => null]);
});

test('student list shows students', function () {
    $student = Students::factory()->create();

    $this->get('/students')->assertSuccessful()->assertSee($student->first_name);
});

test('student profile shows class details', function () {
    $enrolment = StudentClass::factory()->create(['class_division_id' => $this->division->id]);

    $this->get('/admin/view/student/'.$enrolment->student_id)
        ->assertSuccessful()
        ->assertSee($this->division->division_name)
        ->assertSee('2025-2026');
});

test('student profile works for a student without a class', function () {
    $student = Students::factory()->create();

    $this->get('/admin/view/student/'.$student->id)
        ->assertSuccessful()
        ->assertSee('Not assigned');
});

test('student profile 404s for unknown or invalid ids', function () {
    $this->get('/admin/view/student/999999')->assertNotFound();
    $this->get('/admin/view/student/abc')->assertNotFound();
});

test('an empty parent_id from the form still creates a new parent', function () {
    $this->post('/admin/students', studentPayload(['parent_id' => '']))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(Login::where('email', 'mother@example.test')->exists())->toBeTrue();
});

test('an empty parent_id with no new-parent details is a validation error, not a crash', function () {
    $this->post('/admin/students', studentPayload(['parent_id' => '', 'p_email' => '', 'parent_first_name' => '']))
        ->assertSessionHasErrors(['p_email', 'parent_first_name']);
});

test('the student list links each name to the profile and flags only inactive students', function () {
    $enrolment = StudentClass::factory()->create(['class_division_id' => $this->division->id]);
    $inactive = Students::factory()->create(['first_name' => 'Dormant']);
    $inactive->login->update(['is_active' => false]);

    $this->get('/students')
        ->assertSuccessful()
        ->assertSee(route('admin.students.show', $enrolment->student))
        ->assertSee($this->division->division_name)
        ->assertSeeInOrder(['Dormant', 'Inactive'])
        ->assertSee(route('admin.students.edit', $enrolment->student))
        ->assertDontSee('Deactivate');

    expect(substr_count($this->get('/students')->getContent(), '>Inactive</span>'))->toBe(1);
});

test('a student is deactivated from the edit page', function () {
    $student = Students::factory()->create();

    $this->get("/admin/students/{$student->id}/edit")->assertSee('Deactivate Student');
    $this->from("/admin/students/{$student->id}/edit")->post("/admin/students/{$student->id}/toggle-active")
        ->assertRedirect("/admin/students/{$student->id}/edit");
    $this->get("/admin/students/{$student->id}/edit")->assertSee('Reactivate Student');
});

test('the edit student form is prefilled with the current class', function () {
    $enrolment = StudentClass::factory()->create(['class_division_id' => $this->division->id]);

    $this->get("/admin/students/{$enrolment->student_id}/edit")
        ->assertSuccessful()
        ->assertSee('value="'.$enrolment->student->first_name.'"', false)
        ->assertSee('value="'.$this->division->id.'" selected', false);
});

function studentUpdatePayload(Students $student, array $overrides = []): array
{
    return [
        'first_name' => 'Renamed',
        'last_name' => $student->last_name,
        'dob' => '2013-01-02',
        'gender' => 'other',
        'blood_group' => 'AB-',
        'class_division_id' => test()->division->id,
        'address_line_1' => 'New Street 5',
        'address_line_2' => '',
        'city' => 'Ottawa',
        'province' => 'ON',
        'country' => 'Canada',
        'postal' => 'K1K1K1',
        'email' => $student->login->email,
        'password' => '',
        ...$overrides,
    ];
}

test('admin can edit a student and move them to another division', function () {
    $enrolment = StudentClass::factory()->create(['class_division_id' => $this->division->id]);
    $student = $enrolment->student;
    $newDivision = Divisions::factory()->create();
    $oldHash = $student->login->password;

    $this->put("/admin/students/{$student->id}", studentUpdatePayload($student, ['class_division_id' => $newDivision->id]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.students.show', $student));

    $student->refresh();
    expect($student->first_name)->toBe('Renamed')
        ->and($student->gender)->toBe('other')
        ->and($student->login->password)->toBe($oldHash)
        ->and(StudentClass::where('student_id', $student->id)->count())->toBe(1)
        ->and(StudentClass::where('student_id', $student->id)->value('class_division_id'))->toBe($newDivision->id);
});

test('editing a student without a class enrols them for the active year', function () {
    $student = Students::factory()->create();

    $this->put("/admin/students/{$student->id}", studentUpdatePayload($student))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('student_classes', [
        'student_id' => $student->id,
        'class_division_id' => $this->division->id,
        'academic_year_id' => $this->academicYear->id,
    ]);
});

test('editing a student can change login email and password', function () {
    $student = Students::factory()->create();

    $this->put("/admin/students/{$student->id}", studentUpdatePayload($student, ['email' => 'new@example.test', 'password' => 'new-password']))
        ->assertSessionHasNoErrors();

    $login = $student->fresh()->login;
    expect($login->email)->toBe('new@example.test')
        ->and(Hash::check('new-password', $login->password))->toBeTrue();
});

test('student edit validation', function (array $overrides, string $field) {
    $student = Students::factory()->create();
    $taken = Login::factory()->create(['email' => 'taken@example.test']);

    $this->put("/admin/students/{$student->id}", studentUpdatePayload($student, $overrides))->assertSessionHasErrors($field);
})->with([
    'email of another account' => [['email' => 'taken@example.test'], 'email'],
    'short new password' => [['password' => 'short'], 'password'],
    'missing division' => [['class_division_id' => ''], 'class_division_id'],
    'bad gender' => [['gender' => 'robot'], 'gender'],
]);

test('a student can be deactivated and reactivated', function () {
    $student = Students::factory()->create();

    $this->post("/admin/students/{$student->id}/toggle-active")->assertSessionHas('success');
    expect($student->login->fresh()->is_active)->toBeFalse();

    $this->get("/admin/view/student/{$student->id}")->assertSee('Inactive');

    $this->post("/admin/students/{$student->id}/toggle-active");
    expect($student->login->fresh()->is_active)->toBeTrue();
});

test('the student profile shows real class, attendance and contact details', function () {
    $parent = Parents::factory()->create(['first_name' => 'Rosa', 'area_code' => '416', 'phone_number' => '5551234', 'city' => 'ParentTown']);
    $student = Students::factory()->create([
        'parent_id' => $parent->id, 'gender' => 'other', 'city' => 'StudentVille',
        'address_line_2' => 'Unit 7', 'date_of_birth' => '2014-05-01',
    ]);
    StudentClass::factory()->create(['student_id' => $student->id, 'class_division_id' => $this->division->id]);
    $classTeacher = \App\Models\Teachers::factory()->create(['first_name' => 'Clara', 'last_name' => 'Class']);
    $this->division->teachers()->attach($classTeacher->id, ['class_teacher' => true]);
    \App\Models\Attendance::factory()->create(['student_id' => $student->id, 'division_id' => $this->division->id, 'date' => '2026-01-05', 'status' => 'present']);
    \App\Models\Attendance::factory()->create(['student_id' => $student->id, 'division_id' => $this->division->id, 'date' => '2026-01-06', 'status' => 'absent', 'remark' => 'Dentist']);

    $this->get("/admin/view/student/{$student->id}")
        ->assertSuccessful()
        ->assertSee('<h1 class="mb-0">'.$student->first_name.' '.$student->last_name.'</h1>', false)
        ->assertSee('Clara Class')
        ->assertSee('50% attended')
        ->assertSee('Dentist')
        ->assertSee('(416) 5551234')
        ->assertSee('Other')
        ->assertSee('May 1, 2014')
        ->assertSee('StudentVille')
        ->assertSee('Unit 7')
        ->assertDontSee('ParentTown')
        ->assertDontSee('No teacher has been assigned yet')
        ->assertDontSee('No data found');
});
