<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\StudentClass;
use App\Models\TimetableEntry;
use App\Services\TimetableGrid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $teacher = $request->user()->teacherProfile()->load(['subjects', 'divisions.class']);
        $academicYear = AcademicYear::current();
        $published = (bool) $academicYear?->timetable_published_at;
        $today = now();

        $todaysLessons = $published && in_array($today->isoWeekday(), config('school.days'), true)
            ? TimetableEntry::query()
                ->with(['period', 'subject', 'division.class'])
                ->where('academic_year_id', $academicYear->id)
                ->where('teacher_id', $teacher->id)
                ->where('day', $today->isoWeekday())
                ->get()
                ->sortBy(fn (TimetableEntry $entry) => $entry->period->starts_at)
                ->values()
            : collect();

        $markedToday = $academicYear
            ? Attendance::query()
                ->where('date', $today->toDateString())
                ->whereIn('division_id', $teacher->divisions->pluck('id'))
                ->distinct()
                ->pluck('division_id')
            : collect();

        return view('teacher.dashboard', compact('teacher', 'academicYear', 'published', 'todaysLessons', 'markedToday'));
    }

    public function timetable(Request $request): View
    {
        $teacher = $request->user()->teacherProfile();
        $academicYear = AcademicYear::current();
        $published = (bool) $academicYear?->timetable_published_at;

        return view('teacher.timetable', [
            'teacher' => $teacher,
            'academicYear' => $academicYear,
            'grid' => $published ? TimetableGrid::forTeacher($academicYear, $teacher) : null,
        ]);
    }

    public function classes(Request $request): View
    {
        $teacher = $request->user()->teacherProfile();
        $academicYear = AcademicYear::current();

        $divisions = $teacher->divisions()
            ->with('class')
            ->withCount(['studentClasses as students_count' => fn ($query) => $query->where('academic_year_id', $academicYear?->id)])
            ->get()
            ->sortBy('label')
            ->values();

        $subjectsByDivision = $academicYear
            ? TimetableEntry::query()
                ->with('subject')
                ->where('academic_year_id', $academicYear->id)
                ->where('teacher_id', $teacher->id)
                ->get()
                ->groupBy('division_id')
                ->map(fn ($entries) => $entries->pluck('subject.subject_name')->unique()->sort()->values())
            : collect();

        return view('teacher.classes', compact('teacher', 'academicYear', 'divisions', 'subjectsByDivision'));
    }

    public function roster(Divisions $division): View
    {
        Gate::authorize('teach-division', $division);

        $academicYear = AcademicYear::current();
        $division->load(['class', 'teachers' => fn ($query) => $query->wherePivot('class_teacher', true)]);

        $students = StudentClass::query()
            ->with(['student.parent.login', 'student.login'])
            ->where('class_division_id', $division->id)
            ->where('academic_year_id', $academicYear?->id)
            ->get()
            ->pluck('student')
            ->sortBy(fn ($student) => [$student->first_name, $student->last_name])
            ->values();

        $attendanceRates = Attendance::query()
            ->where('division_id', $division->id)
            ->where('academic_year_id', $academicYear?->id)
            ->selectRaw("student_id, round(100.0 * sum(case when status in ('present','late') then 1 else 0 end) / count(*)) as rate")
            ->groupBy('student_id')
            ->pluck('rate', 'student_id');

        return view('teacher.roster', compact('division', 'academicYear', 'students', 'attendanceRates'));
    }
}
