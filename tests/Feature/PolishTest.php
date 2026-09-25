<?php

/**
 * Design review Step E: confirmation dialogs and cleanup.
 */

use App\Models\AcademicYear;
use App\Models\Login;
use App\Models\Subjects;

test('destructive forms ask through the shared dialog, not a script built from names', function () {
    AcademicYear::factory()->active()->create();
    actingAsRole(Login::ROLE_ADMIN);
    Subjects::factory()->create(['subject_name' => "Children's Literature"]);

    $html = $this->get('/subjects')->assertSuccessful()->getContent();

    expect($html)->toContain('id="confirm-dialog"')
        ->and($html)->toContain('data-confirm-title="Delete Children&#039;s Literature?"')
        ->and($html)->toContain('data-confirm-tone="danger"')
        ->and($html)->not->toContain('return confirm(');
});

test('legacy pages that nothing used are gone', function () {
    expect(view()->exists('welcome'))->toBeFalse()
        ->and(view()->exists('home'))->toBeFalse()
        ->and(view()->exists('student.add_old'))->toBeFalse()
        ->and(view()->exists('layouts.app'))->toBeFalse()
        ->and(class_exists(\App\Http\Controllers\HomeController::class))->toBeFalse();
});
