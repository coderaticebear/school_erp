<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\StudentClass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * School-wide attendance and exam reports for the admin.
 */
class SchoolReports
{
    public function __construct(protected ExamResults $results) {}

    /**
     * Attendance per division between two dates.
     *
     * @return Collection<int, array{division: Divisions, students: int, days: int, records: int, attended: int, percent: float|null}>
     */
    public function attendanceByDivision(AcademicYear $academicYear, Carbon $from, Carbon $to): Collection
    {
        $divisions = Divisions::with('class')->get()->sortBy('label')->values();

        $students = StudentClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->selectRaw('class_division_id, count(*) as students')
            ->groupBy('class_division_id')
            ->pluck('students', 'class_division_id');

        $stats = Attendance::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("division_id, count(distinct date) as days, count(*) as records, sum(case when status in ('present','late') then 1 else 0 end) as attended")
            ->groupBy('division_id')
            ->get()
            ->keyBy('division_id');

        return $divisions->map(function (Divisions $division) use ($students, $stats) {
            $row = $stats[$division->id] ?? null;
            $records = (int) ($row->records ?? 0);
            $attended = (int) ($row->attended ?? 0);

            return [
                'division' => $division,
                'students' => (int) ($students[$division->id] ?? 0),
                'days' => (int) ($row->days ?? 0),
                'records' => $records,
                'attended' => $attended,
                'percent' => $records > 0 ? round(100 * $attended / $records, 1) : null,
            ];
        });
    }

    /**
     * Attendance per student between two dates, lowest percentage first.
     * Without a division, only students below the low-attendance threshold are returned.
     *
     * @return Collection<int, array{student: \App\Models\Students, division: Divisions|null, present: int, late: int, absent: int, excused: int, total: int, percent: float}>
     */
    public function attendanceByStudent(AcademicYear $academicYear, Carbon $from, Carbon $to, ?Divisions $division = null): Collection
    {
        $threshold = (float) config('school.low_attendance_percent');

        $rows = Attendance::query()
            ->with(['student', 'division.class'])
            ->where('academic_year_id', $academicYear->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($division, fn ($query) => $query->where('division_id', $division->id))
            ->get()
            ->groupBy('student_id')
            ->map(function (Collection $records) {
                $counts = $records->countBy('status');
                $total = $records->count();
                $attended = collect(Attendance::ATTENDED)->sum(fn ($status) => $counts[$status] ?? 0);

                return [
                    'student' => $records->first()->student,
                    'division' => $records->sortByDesc('date')->first()->division,
                    ...collect(array_keys(Attendance::STATUSES))->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
                    'total' => $total,
                    'percent' => round(100 * $attended / $total, 1),
                ];
            })
            ->values();

        if (! $division) {
            $rows = $rows->filter(fn ($row) => $row['percent'] < $threshold)->values();
        }

        return $rows->sortBy(fn ($row) => [$row['percent'], $row['student']->first_name])->values();
    }

    /**
     * Pass rate, average and top student per division for an exam.
     *
     * @return Collection<int, array{division: Divisions, students: int, complete: int, passed: int, pass_rate: float|null, average: float|null, top: array<string, mixed>|null}>
     */
    public function examByDivision(Exam $exam): Collection
    {
        return Divisions::with(['class', 'teachers.subjects', 'teachers.login'])->get()->sortBy('label')->values()
            ->map(function (Divisions $division) use ($exam) {
                $rows = $this->results->forDivision($exam, $division)['rows'];
                $complete = $rows->whereNotNull('percent');
                $passed = $complete->where('result', ExamResults::PASS)->count();

                return [
                    'division' => $division,
                    'students' => $rows->count(),
                    'complete' => $complete->count(),
                    'passed' => $passed,
                    'pass_rate' => $complete->isNotEmpty() ? round(100 * $passed / $complete->count(), 1) : null,
                    'average' => $complete->isNotEmpty() ? round($complete->avg('percent'), 1) : null,
                    'top' => $complete->sortByDesc('percent')->first(),
                ];
            });
    }

    /**
     * Average, highest and pass rate per subject across the school for an exam.
     *
     * @return Collection<int, array{subject: string, entries: int, average: float|null, highest: float|null, pass_rate: float|null, absent: int}>
     */
    public function examBySubject(Exam $exam): Collection
    {
        return Mark::query()
            ->join('subjects', 'subjects.id', '=', 'marks.subject_id')
            ->where('marks.exam_id', $exam->id)
            ->selectRaw('subjects.subject_name as subject, count(*) as entries, avg(marks.marks) as average, max(marks.marks) as highest')
            ->selectRaw('sum(case when marks.is_absent then 1 else 0 end) as absent')
            ->selectRaw('sum(case when not marks.is_absent and marks.marks >= ? then 1 else 0 end) as passed', [$exam->pass_marks])
            ->groupBy('subjects.subject_name')
            ->orderBy('subjects.subject_name')
            ->get()
            ->map(fn ($row) => [
                'subject' => $row->subject,
                'entries' => (int) $row->entries,
                'average' => $row->average !== null ? round((float) $row->average, 1) : null,
                'highest' => $row->highest !== null ? (float) $row->highest : null,
                'absent' => (int) $row->absent,
                'pass_rate' => $row->entries > 0 ? round(100 * $row->passed / $row->entries, 1) : null,
            ]);
    }

    /**
     * Today's attendance for each division: who the class teacher is, how many students are
     * enrolled, how many are marked and how many attended.
     *
     * @return Collection<int, array{division: Divisions, classTeacher: string|null, students: int, marked: int, attended: int}>
     */
    public function attendanceTodayByDivision(AcademicYear $academicYear): Collection
    {
        $students = StudentClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->selectRaw('class_division_id, count(*) as students')
            ->groupBy('class_division_id')
            ->pluck('students', 'class_division_id');

        $today = Attendance::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('date', now()->toDateString())
            ->selectRaw("division_id, count(*) as marked, sum(case when status in ('present','late') then 1 else 0 end) as attended")
            ->groupBy('division_id')
            ->get()
            ->keyBy('division_id');

        return Divisions::query()
            ->with(['class', 'teachers' => fn ($query) => $query->wherePivot('class_teacher', true)])
            ->get()
            ->sortBy('label')
            ->values()
            ->map(fn (Divisions $division) => [
                'division' => $division,
                'classTeacher' => $division->teachers->first()?->full_name,
                'students' => (int) ($students[$division->id] ?? 0),
                'marked' => (int) ($today[$division->id]->marked ?? 0),
                'attended' => (int) ($today[$division->id]->attended ?? 0),
            ]);
    }

    /**
     * Today's attendance across the school.
     *
     * @return array{marked_divisions: int, divisions: int, records: int, attended: int, percent: float|null}
     */
    public function attendanceToday(AcademicYear $academicYear): array
    {
        $today = Attendance::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('date', now()->toDateString())
            ->selectRaw("count(distinct division_id) as divisions, count(*) as records, sum(case when status in ('present','late') then 1 else 0 end) as attended")
            ->first();

        $records = (int) ($today->records ?? 0);

        return [
            'marked_divisions' => (int) ($today->divisions ?? 0),
            'divisions' => Divisions::count(),
            'records' => $records,
            'attended' => (int) ($today->attended ?? 0),
            'percent' => $records > 0 ? round(100 * $today->attended / $records, 1) : null,
        ];
    }
}
