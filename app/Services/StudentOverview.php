<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\StudentClass;
use App\Models\Students;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;

/**
 * What a student (or their parent) sees: class, timetable, attendance and published results.
 */
class StudentOverview
{
    public function __construct(protected ExamResults $results) {}

    public function enrolment(Students $student, ?AcademicYear $academicYear): ?StudentClass
    {
        return $academicYear
            ? StudentClass::query()
                ->with(['division.class', 'division.teachers' => fn ($query) => $query->wherePivot('class_teacher', true)])
                ->where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->first()
            : null;
    }

    /**
     * @return array{present: int, late: int, absent: int, excused: int, total: int, percent: float|null}
     */
    public function attendanceSummary(Students $student, ?AcademicYear $academicYear): array
    {
        $counts = $academicYear
            ? Attendance::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
            : collect();

        $summary = collect(array_keys(Attendance::STATUSES))->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all();
        $total = array_sum($summary);
        $attended = collect(Attendance::ATTENDED)->sum(fn ($status) => $summary[$status]);

        return [...$summary, 'total' => $total, 'percent' => $total > 0 ? round(100 * $attended / $total, 1) : null];
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function attendanceRecords(Students $student, ?AcademicYear $academicYear, ?string $month = null): Collection
    {
        if (! $academicYear) {
            return collect();
        }

        return Attendance::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->when($month, fn ($query) => $query->whereRaw("to_char(date, 'YYYY-MM') = ?", [$month]))
            ->orderByDesc('date')
            ->get();
    }

    /**
     * Published exams of the year, newest first, each with the student's result row.
     *
     * @return Collection<int, array{exam: Exam, row: array<string, mixed>|null, class_size: int}>
     */
    public function publishedResults(Students $student, ?AcademicYear $academicYear): Collection
    {
        if (! $academicYear) {
            return collect();
        }

        return Exam::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereNotNull('results_published_at')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->map(function (Exam $exam) use ($student) {
                $card = $this->results->reportCard($exam, $student);

                return ['exam' => $exam, 'row' => $card['row'], 'class_size' => $card['class_size']];
            });
    }

    /**
     * @return Collection<int, TimetableEntry>
     */
    public function todaysLessons(?StudentClass $enrolment, ?AcademicYear $academicYear): Collection
    {
        $today = now()->isoWeekday();

        if (! $enrolment || ! $academicYear?->timetable_published_at || ! in_array($today, config('school.days'), true)) {
            return collect();
        }

        return TimetableEntry::query()
            ->with(['period', 'subject', 'teacher'])
            ->where('academic_year_id', $academicYear->id)
            ->where('division_id', $enrolment->class_division_id)
            ->where('day', $today)
            ->get()
            ->sortBy(fn (TimetableEntry $entry) => $entry->period->starts_at)
            ->values();
    }
}
