<?php

namespace App\Services;

use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\StudentClass;
use App\Models\Students;
use App\Models\Subjects;
use Illuminate\Support\Collection;

/**
 * Exam results for a division: every enrolled student's marks per subject,
 * totals, percentage, grade, pass/fail and rank.
 *
 * - Subjects are those the division is taught plus any that already have marks.
 * - An absent subject counts as 0 and fails the student.
 * - A student missing a mark in any subject is "Incomplete" and is not ranked.
 */
class ExamResults
{
    public const PASS = 'Pass';

    public const FAIL = 'Fail';

    public const INCOMPLETE = 'Incomplete';

    public function __construct(protected TimetableGenerator $timetable) {}

    /**
     * @return array{
     *     subjects: Collection<int, Subjects>,
     *     rows: Collection<int, array{student: Students, marks: array<int, Mark|null>, total: float, max_total: int, percent: float|null, grade: string, result: string, rank: int|null}>
     * }
     */
    public function forDivision(Exam $exam, Divisions $division): array
    {
        $students = StudentClass::query()
            ->with('student')
            ->where('class_division_id', $division->id)
            ->where('academic_year_id', $exam->academic_year_id)
            ->get()
            ->pluck('student')
            ->sortBy(fn (Students $student) => [$student->first_name, $student->last_name])
            ->values();

        $marks = Mark::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $studentMarks) => $studentMarks->keyBy('subject_id'));

        $subjectIds = $this->timetable->teachableSubjects($division)->pluck('id')
            ->merge($marks->flatten()->pluck('subject_id'))
            ->unique();

        $subjects = Subjects::query()->whereIn('id', $subjectIds)->orderBy('subject_name')->get();

        $rows = $students->map(fn (Students $student) => $this->row($exam, $student, $subjects, $marks[$student->id] ?? collect()));

        return ['subjects' => $subjects, 'rows' => $this->rank($rows)];
    }

    /**
     * One student's report card for an exam, with rank among their division.
     *
     * @return array{exam: Exam, student: Students, division: Divisions|null, subjects: Collection<int, Subjects>, row: array<string, mixed>|null, class_size: int}
     */
    public function reportCard(Exam $exam, Students $student): array
    {
        $division = StudentClass::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $exam->academic_year_id)
            ->with('division.class')
            ->first()
            ?->division;

        if (! $division) {
            return ['exam' => $exam, 'student' => $student, 'division' => null, 'subjects' => collect(), 'row' => null, 'class_size' => 0];
        }

        $results = $this->forDivision($exam, $division);

        return [
            'exam' => $exam,
            'student' => $student,
            'division' => $division,
            'subjects' => $results['subjects'],
            'row' => $results['rows']->firstWhere('student.id', $student->id),
            'class_size' => $results['rows']->count(),
        ];
    }

    /**
     * @param  Collection<int, Subjects>  $subjects
     * @param  Collection<int, Mark>  $studentMarks  keyed by subject id
     * @return array{student: Students, marks: array<int, Mark|null>, total: float, max_total: int, percent: float|null, grade: string, result: string, rank: int|null}
     */
    protected function row(Exam $exam, Students $student, Collection $subjects, Collection $studentMarks): array
    {
        $bySubject = [];
        $total = 0.0;
        $complete = $subjects->isNotEmpty();
        $failed = false;

        foreach ($subjects as $subject) {
            $mark = $studentMarks[$subject->id] ?? null;
            $bySubject[$subject->id] = $mark;

            if (! $mark) {
                $complete = false;

                continue;
            }

            $total += $mark->is_absent ? 0 : $mark->marks;
            $failed = $failed || $mark->is_absent || $mark->marks < $exam->pass_marks;
        }

        $maxTotal = $subjects->count() * $exam->max_marks;
        $percent = $complete && $maxTotal > 0 ? round(100 * $total / $maxTotal, 2) : null;

        return [
            'student' => $student,
            'marks' => $bySubject,
            'total' => $total,
            'max_total' => $maxTotal,
            'percent' => $percent,
            'grade' => $percent === null ? '—' : ($failed ? 'F' : $exam->gradeFor($percent * $exam->max_marks / 100)),
            'result' => ! $complete ? self::INCOMPLETE : ($failed ? self::FAIL : self::PASS),
            'rank' => null,
        ];
    }

    /**
     * Competition ranking (1, 2, 2, 4) by percentage among complete results.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function rank(Collection $rows): Collection
    {
        $ranked = $rows->filter(fn ($row) => $row['percent'] !== null)->sortByDesc('percent')->values();

        $ranks = [];
        foreach ($ranked as $position => $row) {
            $previous = $ranked[$position - 1] ?? null;
            $ranks[$row['student']->id] = $previous && $previous['percent'] === $row['percent']
                ? $ranks[$previous['student']->id]
                : $position + 1;
        }

        return $rows->map(fn ($row) => [...$row, 'rank' => $ranks[$row['student']->id] ?? null]);
    }
}
