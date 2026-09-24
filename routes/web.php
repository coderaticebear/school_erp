<?php

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\DivisionTeacherController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\PeriodController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MarksController;
use App\Http\Controllers\ParentController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\Teacher\AttendanceController as TeacherAttendanceController;
use App\Http\Controllers\Teacher\PortalController as TeacherPortalController;
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
Route::middleware(['auth', 'role:'.Login::ROLE_ADMIN])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');

    // Students
    Route::get('/students', [StudentController::class, 'getStudent'])->name('admin.students.index');
    Route::get('/admin/addStudent', [AdminController::class, 'addStudent'])->name('admin.addStudent');
    Route::post('/admin/students', [StudentController::class, 'store'])->name('admin.students.store');
    Route::get('/admin/view/student/{id}', [AdminController::class, 'viewStudent'])->name('admin.students.show');
    Route::get('/admin/students/{student}/edit', [StudentController::class, 'edit'])->name('admin.students.edit');
    Route::put('/admin/students/{student}', [StudentController::class, 'update'])->name('admin.students.update');
    Route::post('/admin/students/{student}/toggle-active', [StudentController::class, 'toggleActive'])->name('admin.students.toggle-active');
    Route::post('/admin/getParentByEmail', [AdminController::class, 'getParentByEmail']);

    // Teachers
    Route::get('/teachers', [TeacherController::class, 'getTeacher'])->name('admin.teachers.index');
    Route::get('/teachers/{teacher}', [TeacherController::class, 'viewTeacher'])->whereNumber('teacher')->name('admin.teachers.show');
    Route::get('/admin/teachers/create', [TeacherController::class, 'create'])->name('admin.teachers.create');
    Route::post('/admin/teachers', [TeacherController::class, 'store'])->name('admin.teachers.store');
    Route::get('/admin/teachers/{teacher}/edit', [TeacherController::class, 'edit'])->name('admin.teachers.edit');
    Route::put('/admin/teachers/{teacher}', [TeacherController::class, 'update'])->name('admin.teachers.update');
    Route::post('/admin/teachers/{teacher}/toggle-active', [TeacherController::class, 'toggleActive'])->name('admin.teachers.toggle-active');

    // Subjects
    Route::get('/subjects', [SubjectController::class, 'index'])->name('admin.subjects.index');
    Route::post('/admin/subjects', [SubjectController::class, 'store'])->name('admin.subjects.store');
    Route::put('/admin/subjects/{subject}', [SubjectController::class, 'update'])->name('admin.subjects.update');
    Route::delete('/admin/subjects/{subject}', [SubjectController::class, 'destroy'])->name('admin.subjects.destroy');

    // Academic years
    Route::get('/admin/academic-years', [AcademicYearController::class, 'index'])->name('admin.academic-years.index');
    Route::post('/admin/academic-years', [AcademicYearController::class, 'store'])->name('admin.academic-years.store');
    Route::put('/admin/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('admin.academic-years.update');
    Route::post('/admin/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('admin.academic-years.activate');
    Route::delete('/admin/academic-years/{academicYear}', [AcademicYearController::class, 'destroy'])->name('admin.academic-years.destroy');

    // Classes, divisions and division teachers
    Route::get('/admin/classes', [ClassController::class, 'index'])->name('admin.classes.index');
    Route::post('/admin/classes', [ClassController::class, 'store'])->name('admin.classes.store');
    Route::put('/admin/classes/{class}', [ClassController::class, 'update'])->name('admin.classes.update');
    Route::delete('/admin/classes/{class}', [ClassController::class, 'destroy'])->name('admin.classes.destroy');
    Route::post('/admin/divisions', [ClassController::class, 'storeDivision'])->name('admin.divisions.store');
    Route::put('/admin/divisions/{division}', [ClassController::class, 'updateDivision'])->name('admin.divisions.update');
    Route::delete('/admin/divisions/{division}', [ClassController::class, 'destroyDivision'])->name('admin.divisions.destroy');
    Route::get('/admin/divisions/{division}/teachers', [DivisionTeacherController::class, 'edit'])->name('admin.divisions.teachers.edit');
    Route::put('/admin/divisions/{division}/teachers', [DivisionTeacherController::class, 'update'])->name('admin.divisions.teachers.update');

    // Timetable
    Route::get('/admin/timetable', [TimeTableController::class, 'index'])->name('admin.timetable');
    Route::post('/admin/timetable/generate', [TimeTableController::class, 'generate'])->name('admin.timetable.generate');
    Route::put('/admin/timetable/entries', [TimeTableController::class, 'saveEntry'])->name('admin.timetable.entries.save');
    Route::delete('/admin/timetable/entries', [TimeTableController::class, 'clearEntry'])->name('admin.timetable.entries.clear');
    Route::post('/admin/timetable/publish', [TimeTableController::class, 'publish'])->name('admin.timetable.publish');
    Route::get('/admin/timetable/export', [TimeTableController::class, 'export'])->name('admin.timetable.export');

    // Exams and results
    Route::get('/admin/exams', [ExamController::class, 'index'])->name('admin.exams.index');
    Route::post('/admin/exams', [ExamController::class, 'store'])->name('admin.exams.store');
    Route::get('/admin/exams/{exam}', [ExamController::class, 'show'])->name('admin.exams.show');
    Route::put('/admin/exams/{exam}', [ExamController::class, 'update'])->name('admin.exams.update');
    Route::delete('/admin/exams/{exam}', [ExamController::class, 'destroy'])->name('admin.exams.destroy');
    Route::post('/admin/exams/{exam}/publish', [ExamController::class, 'togglePublish'])->name('admin.exams.publish');
    Route::get('/admin/exams/{exam}/divisions/{division}', [ExamController::class, 'results'])->name('admin.exams.results');
    Route::get('/admin/exams/{exam}/students/{student}', [ExamController::class, 'reportCard'])->name('admin.exams.report-card');

    // Bell schedule
    Route::get('/admin/periods', [PeriodController::class, 'index'])->name('admin.periods.index');
    Route::post('/admin/periods', [PeriodController::class, 'store'])->name('admin.periods.store');
    Route::put('/admin/periods/{period}', [PeriodController::class, 'update'])->name('admin.periods.update');
    Route::delete('/admin/periods/{period}', [PeriodController::class, 'destroy'])->name('admin.periods.destroy');
});

// Marks entry (admins: any division and subject; teachers: their own, checked by the enter-marks gate)
Route::middleware(['auth', 'role:'.Login::ROLE_ADMIN.','.Login::ROLE_TEACHER])->name('marks.')->group(function () {
    Route::get('/marks', [MarksController::class, 'index'])->name('index');
    Route::get('/marks/{exam}/{division}/{subject}', [MarksController::class, 'sheet'])->name('sheet');
    Route::post('/marks/{exam}/{division}/{subject}', [MarksController::class, 'save'])->name('save');
});

// Teacher routes
Route::middleware(['auth', 'role:'.Login::ROLE_TEACHER])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/dashboard', [TeacherPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/timetable', [TeacherPortalController::class, 'timetable'])->name('timetable');
    Route::get('/classes', [TeacherPortalController::class, 'classes'])->name('classes');
    Route::get('/classes/{division}', [TeacherPortalController::class, 'roster'])->name('classes.show');
    Route::get('/attendance', [TeacherAttendanceController::class, 'index'])->name('attendance');
    Route::post('/attendance', [TeacherAttendanceController::class, 'store'])->name('attendance.store');
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
