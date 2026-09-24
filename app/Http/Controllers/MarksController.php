<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveMarksRequest;
use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Login;
use App\Models\Mark;
use App\Models\StudentClass;
use App\Models\Subjects;
use App\Services\TimetableGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Marks entry, shared by admins (any division and subject) and teachers (their own).
 */
class MarksController extends Controller
{
    public function __construct(protected TimetableGenerator $timetable) {}

    /**
     * Pick an exam, then a division and subject to enter marks for.
     */
    public function index(Request $request): View
    {
        $academicYear = AcademicYear::current();
        $exams = $academicYear
            ? Exam::query()->where('academic_year_id', $academicYear->id)->orderByDesc('starts_on')->orderByDesc('id')->get()
            : collect();
        $exam = $exams->firstWhere('id', (int) $request->query('exam')) ?? $exams->first();

        $sheets = $exam ? $this->sheetsFor($request->user(), $exam) : collect();

        return view('marks.index', compact('academicYear', 'exams', 'exam', 'sheets'));
    }

    public function sheet(Exam $exam, Divisions $division, Subjects $subject): View
    {
        Gate::authorize('enter-marks', [$division, $subject]);

        $division->load('class');

        $students = StudentClass::query()
            ->with('student')
            ->where('class_division_id', $division->id)
            ->where('academic_year_id', $exam->academic_year_id)
            ->get()
            ->pluck('student')
            ->sortBy(fn ($student) => [$student->first_name, $student->last_name])
            ->values();

        $existing = Mark::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $subject->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        return view('marks.sheet', compact('exam', 'division', 'subject', 'students', 'existing'));
    }

    /**
     * Save a subject's marks; a blank row (no marks, not absent) removes that student's mark.
     */
    public function save(SaveMarksRequest $request, Exam $exam, Divisions $division, Subjects $subject): RedirectResponse
    {
        $rows = $request->validated('marks');
        $enteredBy = $request->user()->id;

        DB::transaction(function () use ($rows, $exam, $division, $subject, $enteredBy) {
            foreach ($rows as $studentId => $row) {
                $keys = ['exam_id' => $exam->id, 'student_id' => (int) $studentId, 'subject_id' => $subject->id];

                if (! $row['absent'] && blank($row['value'] ?? null)) {
                    Mark::query()->where($keys)->delete();

                    continue;
                }

                Mark::updateOrCreate($keys, [
                    'division_id' => $division->id,
                    'marks' => $row['absent'] ? null : (float) $row['value'],
                    'is_absent' => $row['absent'],
                    'remark' => $row['remark'] ?? null,
                    'entered_by' => $enteredBy,
                ]);
            }
        });

        return redirect()
            ->route('marks.sheet', [$exam, $division, $subject])
            ->with('success', "{$subject->subject_name} marks saved for {$division->label}.");
    }

    /**
     * The (division, subject) sheets this user may fill in, with progress.
     *
     * @return Collection<int, array{division: Divisions, subject: Subjects, entered: int, students: int}>
     */
    protected function sheetsFor(Login $user, Exam $exam): Collection
    {
        $relations = ['class', 'teachers.subjects', 'teachers.login'];
        $divisions = $user->role === Login::ROLE_ADMIN
            ? Divisions::with($relations)->get()
            : $user->teacherProfile()->divisions()->with($relations)->get();

        $mySubjectIds = $user->role === Login::ROLE_ADMIN ? null : $user->teacher->subjects()->pluck('subjects.id');

        $studentCounts = StudentClass::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->selectRaw('class_division_id, count(*) as students')
            ->groupBy('class_division_id')
            ->pluck('students', 'class_division_id');

        $entered = Mark::query()
            ->where('exam_id', $exam->id)
            ->selectRaw('division_id, subject_id, count(*) as entered')
            ->groupBy('division_id', 'subject_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->division_id}:{$row->subject_id}");

        return $divisions
            ->flatMap(fn (Divisions $division) => $this->timetable->teachableSubjects($division)
                ->when($mySubjectIds, fn ($subjects) => $subjects->whereIn('id', $mySubjectIds))
                ->map(fn (Subjects $subject) => [
                    'division' => $division,
                    'subject' => $subject,
                    'entered' => (int) ($entered["{$division->id}:{$subject->id}"]->entered ?? 0),
                    'students' => (int) ($studentCounts[$division->id] ?? 0),
                ]))
            ->sortBy(fn ($sheet) => [$sheet['division']->label, $sheet['subject']->subject_name])
            ->values();
    }
}
