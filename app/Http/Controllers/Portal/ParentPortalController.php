<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Students;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ParentPortalController extends Controller
{
    use StudentRecords;

    /**
     * Every child of the parent, with class, attendance and latest result.
     */
    public function dashboard(Request $request): View
    {
        $parent = $request->user()->parentProfile();
        $academicYear = AcademicYear::current();

        $children = $parent->students()->orderBy('first_name')->get()->map(fn (Students $child) => [
            'student' => $child,
            'enrolment' => $this->overview()->enrolment($child, $academicYear),
            'attendance' => $this->overview()->attendanceSummary($child, $academicYear),
            'latestResult' => $this->overview()->publishedResults($child, $academicYear)->first(),
        ]);

        return view('parent.dashboard', compact('parent', 'academicYear', 'children'));
    }

    public function child(Students $student): View
    {
        Gate::authorize('view-student', $student);

        return $this->studentHome($student);
    }

    public function timetable(Students $student): View
    {
        Gate::authorize('view-student', $student);

        return $this->studentTimetable($student);
    }

    public function attendance(Request $request, Students $student): View
    {
        Gate::authorize('view-student', $student);

        return $this->studentAttendance($student, $request->query('month'));
    }

    public function results(Students $student): View
    {
        Gate::authorize('view-student', $student);

        return $this->studentResults($student);
    }

    public function reportCard(Students $student, Exam $exam): View
    {
        Gate::authorize('view-student', $student);

        return $this->studentReportCard($student, $exam);
    }

    protected function portalRoutes(Students $student): array
    {
        return ['routePrefix' => 'parent.children.', 'routeParams' => [$student]];
    }
}
