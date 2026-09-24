<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveAttendanceRequest;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\StudentClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * The marking sheet for one of the teacher's divisions on one date.
     */
    public function index(Request $request): View
    {
        $teacher = $request->user()->teacherProfile();
        $academicYear = AcademicYear::current();
        $divisions = $teacher->divisions()->with('class')->get()->sortBy('label')->values();

        $division = $divisions->firstWhere('id', (int) $request->query('division')) ?? $divisions->first();

        if ($division) {
            Gate::authorize('teach-division', $division);
        }

        $date = $this->requestedDate($request);
        $isSchoolDay = in_array($date->isoWeekday(), config('school.days'), true);

        $students = collect();
        $existing = collect();
        $recent = collect();

        if ($division && $academicYear) {
            $students = StudentClass::query()
                ->with('student')
                ->where('class_division_id', $division->id)
                ->where('academic_year_id', $academicYear->id)
                ->get()
                ->pluck('student')
                ->sortBy(fn ($student) => [$student->first_name, $student->last_name])
                ->values();

            $existing = Attendance::query()
                ->with('markedBy.teacher')
                ->where('division_id', $division->id)
                ->where('date', $date->toDateString())
                ->get()
                ->keyBy('student_id');

            $recent = Attendance::query()
                ->where('division_id', $division->id)
                ->where('academic_year_id', $academicYear->id)
                ->selectRaw("date, count(*) as total, sum(case when status in ('present','late') then 1 else 0 end) as attended")
                ->groupBy('date')
                ->orderByDesc('date')
                ->limit(10)
                ->get();
        }

        return view('teacher.attendance', compact('academicYear', 'divisions', 'division', 'date', 'isSchoolDay', 'students', 'existing', 'recent'));
    }

    public function store(SaveAttendanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $academicYear = $request->academicYear();
        $markedBy = $request->user()->id;

        DB::transaction(function () use ($validated, $academicYear, $markedBy) {
            foreach ($validated['attendance'] as $studentId => $row) {
                Attendance::updateOrCreate(
                    ['student_id' => (int) $studentId, 'date' => $validated['date']],
                    [
                        'academic_year_id' => $academicYear->id,
                        'division_id' => $validated['division_id'],
                        'status' => $row['status'],
                        'remark' => $row['remark'] ?? null,
                        'marked_by' => $markedBy,
                    ],
                );
            }
        });

        return redirect()
            ->route('teacher.attendance', ['division' => $validated['division_id'], 'date' => $validated['date']])
            ->with('success', 'Attendance saved for '.count($validated['attendance']).' students.');
    }

    protected function requestedDate(Request $request): Carbon
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', (string) $request->query('date', now()->toDateString()))->startOfDay();
        } catch (\Throwable) {
            $date = now()->startOfDay();
        }

        return $date->isFuture() ? now()->startOfDay() : $date;
    }
}
