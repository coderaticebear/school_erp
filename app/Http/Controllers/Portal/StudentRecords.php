<?php

namespace App\Http\Controllers\Portal;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Students;
use App\Services\ExamResults;
use App\Services\StudentOverview;
use App\Services\TimetableGrid;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Pages about one student, shared by the student portal and the parent portal.
 * Each controller supplies the route names/params so links stay inside its portal.
 */
trait StudentRecords
{
    /**
     * @return array{routePrefix: string, routeParams: array<int, mixed>}
     */
    abstract protected function portalRoutes(Students $student): array;

    protected function overview(): StudentOverview
    {
        return app(StudentOverview::class);
    }

    protected function studentHome(Students $student, string $view = 'portal.overview'): View
    {
        $academicYear = AcademicYear::current();
        $enrolment = $this->overview()->enrolment($student, $academicYear);

        return view($view, [
            ...$this->portalRoutes($student),
            'student' => $student,
            'academicYear' => $academicYear,
            'enrolment' => $enrolment,
            'attendance' => $this->overview()->attendanceSummary($student, $academicYear),
            'recentAbsences' => $this->overview()->attendanceRecords($student, $academicYear)
                ->whereIn('status', ['absent', 'late'])->take(5),
            'latestResult' => $this->overview()->publishedResults($student, $academicYear)->first(),
            'todaysLessons' => $this->overview()->todaysLessons($enrolment, $academicYear),
        ]);
    }

    protected function studentTimetable(Students $student): View
    {
        $academicYear = AcademicYear::current();
        $enrolment = $this->overview()->enrolment($student, $academicYear);

        return view('portal.timetable', [
            ...$this->portalRoutes($student),
            'student' => $student,
            'academicYear' => $academicYear,
            'enrolment' => $enrolment,
            'grid' => $enrolment && $academicYear->timetable_published_at
                ? TimetableGrid::forDivision($academicYear, $enrolment->division)
                : null,
        ]);
    }

    protected function studentAttendance(Students $student, ?string $month): View
    {
        $academicYear = AcademicYear::current();
        $month = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null;

        return view('portal.attendance', [
            ...$this->portalRoutes($student),
            'student' => $student,
            'academicYear' => $academicYear,
            'summary' => $this->overview()->attendanceSummary($student, $academicYear),
            'records' => $this->overview()->attendanceRecords($student, $academicYear, $month),
            'months' => $this->overview()->attendanceRecords($student, $academicYear)
                ->map(fn ($record) => $record->date->format('Y-m'))->unique()->values(),
            'month' => $month,
            'monthLabel' => fn (string $value) => Carbon::createFromFormat('Y-m', $value)->format('F Y'),
        ]);
    }

    protected function studentResults(Students $student): View
    {
        $academicYear = AcademicYear::current();

        return view('portal.results', [
            ...$this->portalRoutes($student),
            'student' => $student,
            'academicYear' => $academicYear,
            'results' => $this->overview()->publishedResults($student, $academicYear),
        ]);
    }

    protected function studentReportCard(Students $student, Exam $exam): View
    {
        abort_unless($exam->isPublished(), 404);

        ['routePrefix' => $prefix, 'routeParams' => $params] = $this->portalRoutes($student);

        return view('exams.report-card', app(ExamResults::class)->reportCard($exam, $student) + [
            'backUrl' => route($prefix.'results', $params),
        ]);
    }
}
