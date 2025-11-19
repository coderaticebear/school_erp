<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ParentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SubjectController;

Auth::routes();

// Redirect root '/' based on login
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect('/login');
});

// Fallback dashboard route that redirects based on role
Route::get('/dashboard', function () {
    $user = auth()->user();
    switch ($user->role) {
        case 1:
            return redirect()->route('admin.dashboard');
        case 2:
            return redirect()->route('teacher.dashboard');
        case 3:
            return redirect()->route('student.dashboard');
        case 4:
            return redirect()->route('parent.dashboard');
        default:
            return redirect('/login');
    }
})->middleware('auth')->name('dashboard');

// Admin routes
Route::group(['middleware' => ['auth', 'role:1']], function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
});

// Teacher routes
Route::group(['middleware' => ['auth', 'role:2']], function () {
    Route::get('/teacher/dashboard', [TeacherController::class, 'index'])->name('teacher.dashboard');
});

// Student routes
Route::group(['middleware' => ['auth', 'role:3']], function () {
    Route::get('/student/dashboard', [StudentController::class, 'index'])->name('student.dashboard');
});

// Parent routes
Route::group(['middleware' => ['auth', 'role:4']], function () {
    Route::get('/parent/dashboard', [ParentController::class, 'index'])->name('parent.dashboard');
});
Route::get('/logout', [LoginController::class, 'logout']);

Route::get('/students', [StudentController::class, 'getStudent']);
Route::get('/teachers', [TeacherController::class, 'getTeacher']);
Route::get('/subjects', [SubjectController::class, 'getSubject']);
Route::get('/teachers/{id}', [TeacherController::class, 'viewTeacher']);
