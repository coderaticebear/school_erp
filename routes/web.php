<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ParentController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TimeTableController;
use App\Models\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes(['register' => false]);

// Redirect root '/' based on login
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect('/login');
});

// Fallback dashboard route that redirects based on role
Route::get('/dashboard', function () {
    $user = auth()->user();

    return match ((int) $user->role) {
        Login::ROLE_ADMIN => redirect()->route('admin.dashboard'),
        Login::ROLE_TEACHER => redirect()->route('teacher.dashboard'),
        Login::ROLE_STUDENT => redirect()->route('student.dashboard'),
        Login::ROLE_PARENT => redirect()->route('parent.dashboard'),
        default => abort(403),
    };
})->middleware('auth')->name('dashboard');

// Admin routes
Route::group(['middleware' => ['auth', 'role:1']], function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/addStudent', [AdminController::class, 'addStudent'])->name('admin.addStudent');
    Route::post('/admin/students', [StudentController::class, 'store'])->name('admin.students.store');
    Route::post('/admin/getParentByEmail', [AdminController::class, 'getParentByEmail']);
    Route::get('/students', [StudentController::class, 'getStudent']);
    Route::get('/teachers', [TeacherController::class, 'getTeacher']);
    Route::get('/subjects', [SubjectController::class, 'getSubject']);
    Route::get('/teachers/{id}', [TeacherController::class, 'viewTeacher']);
    Route::get('/admin/view/student/{id}', [AdminController::class, 'viewStudent']);
    Route::get('/admin/timetable', [AdminController::class, 'timeTableManager']);
    Route::post('/admin/generateTimeTable', [TimeTableController::class, 'generateTimeTable']);
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
