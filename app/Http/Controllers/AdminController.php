<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Parents;
use App\Models\StudentClass;
use App\Models\Students;
use App\Models\Teachers;
use App\Pipelines\SanitizeInput;
use App\Services\SchoolReports;
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

    public function viewStudent($id)
    {
        $id = SanitizeInput::run([$id])[0];

        if (! ctype_digit((string) $id)) {
            abort(404, 'Invalid student ID');
        }

        $academicYear = AcademicYear::current();
        $student = Students::with(['parent.login', 'login'])->findOrFail($id);
        $parent = $student->parent;
        $classDetails = $academicYear
            ? StudentClass::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->with(['division.class'])
                ->first()
            : null;

        $data = [
            'student_id' => $student->id,
            'roll_number' => $student->roll_number ?? 'N/A',
            'is_active' => (bool) $student->login?->is_active,
            'student_name' => "{$student->first_name} {$student->last_name}",
            'parent_name' => $parent
                ? "{$parent->first_name} {$parent->last_name}"
                : 'N/A',
            'parent_email' => optional($parent?->login)->email ?? 'N/A',
            'address_line_1' => $parent->address_line_1 ?? 'N/A',
            'address_line_2' => $parent->address_line_2 ?? 'N/A',
            'city' => $parent->city ?? 'N/A',
            'province' => $parent->province ?? 'N/A',
            'country' => $parent->country ?? 'N/A',
            'postal' => $parent->postal ?? 'N/A',
            'date_of_birth' => date('j F Y', strtotime($student->date_of_birth)) ?? 'N/A',
            'gender' => $student->gender ?? 'N/A',
            'blood_group' => $student->blood_group ?? 'N/A',
            'division_name' => $classDetails?->division?->division_name ?? 'Not assigned',
            'class_name' => $classDetails?->division?->class?->class_name ?? 'Not assigned',
            'academic_year' => $academicYear?->year ?? 'N/A',
        ];

        $exams = $academicYear
            ? Exam::query()->where('academic_year_id', $academicYear->id)->orderByDesc('starts_on')->get()
            : collect();

        return view('student.profile', compact('data', 'exams', 'student'));
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
            ->value('id');

        return response()->json([
            'parent_id' => $parent,
        ]);
    }
}
