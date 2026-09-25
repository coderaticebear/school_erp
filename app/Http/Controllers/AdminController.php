<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Parents;
use App\Models\Students;
use App\Models\Teachers;
use App\Pipelines\SanitizeInput;
use App\Services\SchoolReports;
use App\Services\StudentOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    //

    public function index(SchoolReports $reports): View
    {
        $academicYear = AcademicYear::current();

        $latestExam = $academicYear
            ? Exam::query()->where('academic_year_id', $academicYear->id)->whereNotNull('results_published_at')->latest('results_published_at')->first()
            : null;

        $examSummary = $latestExam ? $reports->examByDivision($latestExam) : collect();
        $complete = $examSummary->sum('complete');

        return view('admin.dashboard', [
            'academicYear' => $academicYear,
            'studentCount' => Students::whereHas('login', fn ($query) => $query->where('is_active', true))->count(),
            'teacherCount' => Teachers::whereHas('login', fn ($query) => $query->where('is_active', true))->count(),
            'today' => $academicYear ? $reports->attendanceToday($academicYear) : null,
            'lowAttendance' => $academicYear ? $reports->attendanceByStudent($academicYear, now()->startOfMonth(), now()->startOfDay())->take(5) : collect(),
            'latestExam' => $latestExam,
            'passRate' => $complete > 0 ? round(100 * $examSummary->sum('passed') / $complete, 1) : null,
            'timetablePublished' => (bool) $academicYear?->timetable_published_at,
        ]);
    }

    public function viewStudent($id, StudentOverview $overview): View
    {
        $id = SanitizeInput::run([$id])[0];

        if (! ctype_digit((string) $id)) {
            abort(404, 'Invalid student ID');
        }

        $academicYear = AcademicYear::current();
        $student = Students::with(['parent.login', 'login'])->findOrFail($id);

        $exams = $academicYear
            ? Exam::query()->where('academic_year_id', $academicYear->id)->orderByDesc('starts_on')->orderByDesc('id')->get()
            : collect();

        return view('student.profile', [
            'student' => $student,
            'parent' => $student->parent,
            'academicYear' => $academicYear,
            'enrolment' => $overview->enrolment($student, $academicYear),
            'attendance' => $overview->attendanceSummary($student, $academicYear),
            'recentAbsences' => $overview->attendanceRecords($student, $academicYear)
                ->whereIn('status', [Attendance::ABSENT, Attendance::LATE])
                ->take(5),
            'exams' => $exams,
        ]);
    }

    public function addStudent()
    {
        $parents = Parents::all();
        $data = $parents->map(function ($parent) {
            return [
                'id' => $parent->id,
                'name' => $parent->first_name.' '.$parent->last_name,
            ];
        })->toArray();

        $divisions = Divisions::with('class')
            ->get()
            ->sortBy(fn (Divisions $division) => [$division->class->class_name ?? '', $division->division_name])
            ->values();

        return view('student.add')->with(['data' => $data, 'divisions' => $divisions]);
    }

    public function getParentByEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
        ]);

        $parent = Parents::query()
            ->whereHas('login', fn ($query) => $query
                ->whereRaw('lower(email) = ?', [strtolower($validated['email'])])
                ->where('role', Login::ROLE_PARENT))
            ->first(['id', 'first_name', 'last_name']);

        return response()->json([
            'parent_id' => $parent?->id,
            'name' => $parent ? trim("{$parent->first_name} {$parent->last_name}") : null,
        ]);
    }
}
