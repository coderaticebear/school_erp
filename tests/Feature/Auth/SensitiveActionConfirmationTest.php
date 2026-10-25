<?php

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Students;
use App\Models\Subjects;
use App\Models\Teachers;
use Illuminate\Support\Facades\Hash;

/*
 * SEC-06 (FR-2FA-14, FR-2FA-20): an admin enters the password again before a CSV export,
 * a deactivation, or a new password for another person. A confirmation is good for 15 minutes.
 */

beforeEach(function () {
    $this->year = AcademicYear::factory()->active()->create();
    $this->division = Divisions::factory()->create();
    $this->exam = Exam::factory()->create(['academic_year_id' => $this->year->id]);
    $this->admin = actingAsRole(Login::ROLE_ADMIN);
});

/**
 * The requests that need a recent password: [method, url].
 *
 * @return array<string, array{0: string, 1: Closure(): string}>
 */
function sensitiveRequests(): array
{
    return [
        'deactivate a student' => ['post', fn () => '/admin/students/'.Students::factory()->create()->id.'/toggle-active'],
        'deactivate a teacher' => ['post', fn () => '/admin/teachers/'.Teachers::factory()->create()->id.'/toggle-active'],
        'timetable CSV' => ['get', fn () => '/admin/timetable/export?division='.test()->division->id],
        'exam results CSV' => ['get', fn () => '/admin/exams/'.test()->exam->id.'/divisions/'.test()->division->id.'?format=csv'],
        'attendance report CSV' => ['get', fn () => '/admin/reports/attendance?format=csv'],
        'exam report CSV' => ['get', fn () => '/admin/reports/exams?exam='.test()->exam->id.'&format=csv'],
    ];
}

dataset('sensitive requests', sensitiveRequests());

test('a sensitive action redirects to the password page without a recent confirmation', function (string $method, Closure $url) {
    $this->{$method}($url())->assertRedirect(route('password.confirm'));
})->with('sensitive requests');

test('a sensitive action redirects again after 15 minutes', function (string $method, Closure $url) {
    confirmPassword(secondsAgo: 16 * 60);

    $this->{$method}($url())->assertRedirect(route('password.confirm'));
})->with('sensitive requests');

test('a sensitive action works within 15 minutes of the confirmation', function (string $method, Closure $url) {
    confirmPassword(secondsAgo: 14 * 60);

    $response = $this->{$method}($url());

    expect($response->headers->get('Location'))->not->toBe(route('password.confirm'));
    expect($response->getStatusCode())->toBeLessThan(400);
})->with('sensitive requests');

test('a deactivation without a recent password changes nothing', function () {
    $student = Students::factory()->create();

    $this->post("/admin/students/{$student->id}/toggle-active")->assertRedirect(route('password.confirm'));

    expect($student->login->fresh()->is_active)->toBeTrue();
});

test('after the password the admin returns to the CSV export', function () {
    $url = '/admin/reports/attendance?format=csv';
    $this->get($url)->assertRedirect(route('password.confirm'));

    $this->post(route('password.confirm'), ['password' => 'password'])->assertRedirect(url($url));
    $this->get($url)->assertSuccessful()->assertDownload();
});

test('the report and results pages open without a password', function (Closure $url) {
    $this->get($url())->assertSuccessful();
})->with([
    'exam results' => [fn () => '/admin/exams/'.test()->exam->id.'/divisions/'.test()->division->id],
    'attendance report' => [fn () => '/admin/reports/attendance'],
    'exam report' => [fn () => '/admin/reports/exams?exam='.test()->exam->id],
]);

/**
 * Valid Update Student data for $student.
 *
 * @return array<string, mixed>
 */
function confirmStudentPayload(Students $student, array $overrides = []): array
{
    return [
        'first_name' => 'Renamed',
        'last_name' => $student->last_name,
        'dob' => '2013-01-02',
        'gender' => 'other',
        'blood_group' => 'AB-',
        'class_division_id' => test()->division->id,
        'address_line_1' => 'New Street 5',
        'city' => 'Ottawa',
        'province' => 'ON',
        'country' => 'Canada',
        'postal' => 'K1K1K1',
        'email' => $student->login->email,
        'password' => '',
        ...$overrides,
    ];
}

/**
 * Valid Update Teacher data for $teacher.
 *
 * @return array<string, mixed>
 */
function confirmTeacherPayload(Teachers $teacher, array $overrides = []): array
{
    return [
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'address_line_1' => '1 Navy Way',
        'city' => 'Arlington',
        'province' => 'VA',
        'country' => 'USA',
        'postal' => '22201',
        'email' => $teacher->login->email,
        'password' => '',
        'subject_ids' => [Subjects::factory()->create()->id],
        ...$overrides,
    ];
}

// The edit forms that can set a password for another person. Each closure returns [url, payload, login].
dataset('edit forms', [
    'Edit Student' => [function (array $overrides): array {
        $student = Students::factory()->create();

        return ["/admin/students/{$student->id}", confirmStudentPayload($student, $overrides), $student->login];
    }],
    'Edit Teacher' => [function (array $overrides): array {
        $teacher = Teachers::factory()->create();

        return ["/admin/teachers/{$teacher->id}", confirmTeacherPayload($teacher, $overrides), $teacher->login];
    }],
]);

test('a new password needs the admin password', function (Closure $form, array $overrides) {
    [$url, $payload, $login] = $form(['password' => 'Brand-New-Pass-9911', ...$overrides]);
    $oldHash = $login->password;

    $this->put($url, $payload)->assertSessionHasErrors('current_password');

    expect($login->fresh()->password)->toBe($oldHash);
})->with('edit forms')->with([
    'missing' => [[]],
    'empty' => [['current_password' => '']],
    'wrong' => [['current_password' => 'not-the-admin-password']],
]);

test('a new password is saved with the correct admin password', function (Closure $form) {
    [$url, $payload, $login] = $form(['password' => 'Brand-New-Pass-9911', 'current_password' => 'password']);

    $this->put($url, $payload)->assertSessionHasNoErrors();

    expect(Hash::check('Brand-New-Pass-9911', $login->fresh()->password))->toBeTrue();
})->with('edit forms');

test('an edit with no new password needs no admin password', function (Closure $form, array $overrides) {
    [$url, $payload] = $form($overrides);

    $this->put($url, $payload)->assertSessionHasNoErrors();
})->with('edit forms')->with([
    'field left out' => [[]],
    'wrong value ignored' => [['current_password' => 'not-the-admin-password']],
]);

test('a failed edit form never keeps the passwords in the session', function (Closure $form) {
    [$url, $payload] = $form(['password' => 'Brand-New-Pass-9911', 'current_password' => 'Wrong-Admin-Secret-4471']);

    $this->from($url.'/edit')->put($url, $payload)
        ->assertRedirect($url.'/edit')
        ->assertSessionHasErrors('current_password')
        ->assertSessionHasInput('first_name');

    expectSessionWithoutPasswords(['Brand-New-Pass-9911', 'Wrong-Admin-Secret-4471']);
})->with('edit forms');

test('the edit forms have a labelled Your Password field', function (string $url) {
    $this->get($url)
        ->assertSuccessful()
        ->assertSee('<label for="current-password">Your Password</label>', false)
        ->assertSee('id="current-password" type="password" name="current_password"', false);
})->with([
    'Edit Student' => [fn () => '/admin/students/'.Students::factory()->create()->id.'/edit'],
    'Edit Teacher' => [fn () => '/admin/teachers/'.Teachers::factory()->create()->id.'/edit'],
]);

test('the create forms have no Your Password field', function (string $url) {
    $this->get($url)->assertSuccessful()->assertDontSee('current_password');
})->with([
    'Add Student' => ['/admin/addStudent'],
    'Add Teacher' => ['/admin/teachers/create'],
]);
