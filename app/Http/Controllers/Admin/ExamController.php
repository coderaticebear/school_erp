<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamRequest;
use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\StudentClass;
use App\Models\Students;
use App\Services\ExamResults;
use App\Services\TimetableGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamController extends Controller
{
    public function __construct(protected ExamResults $results, protected TimetableGenerator $timetable) {}

    public function index(): View
    {
        $academicYear = AcademicYear::current();

        $exams = $academicYear
            ? Exam::query()->where('academic_year_id', $academicYear->id)->withCount('marks')->orderByDesc('starts_on')->orderByDesc('id')->get()
            : collect();

        return view('admin.exams.index', compact('academicYear', 'exams'));
    }

    public function store(ExamRequest $request): RedirectResponse
    {
        $academicYear = AcademicYear::current();

        if (! $academicYear) {
            return back()->with('error', 'Activate an academic year before adding exams.');
        }

        $exam = Exam::create([...$request->validated(), 'academic_year_id' => $academicYear->id]);

        return redirect()->route('admin.exams.show', $exam)->with('success', 'Exam added.');
    }

    public function update(ExamRequest $request, Exam $exam): RedirectResponse
    {
        $validated = $request->validated();

        $highest = $exam->marks()->max('marks');
        if ($highest !== null && $validated['max_marks'] < $highest) {
            return back()->with('error', "Maximum marks cannot be lower than the highest mark already entered ({$highest}).");
        }

        $exam->update($validated);

        return back()->with('success', 'Exam updated.');
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        if ($exam->marks()->exists()) {
            return back()->with('error', "{$exam->name} already has marks and cannot be deleted.");
        }

        $exam->delete();

        return redirect()->route('admin.exams.index')->with('success', 'Exam deleted.');
    }

    /**
     * Marks-entry progress for every division and subject.
     */
    public function show(Exam $exam): View
    {
        $divisions = Divisions::with(['class', 'teachers.subjects', 'teachers.login'])->get()->sortBy('label')->values();

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

        $matrix = $divisions->map(fn (Divisions $division) => [
            'division' => $division,
            'students' => (int) ($studentCounts[$division->id] ?? 0),
            'subjects' => $this->timetable->teachableSubjects($division)->map(fn ($subject) => [
                'subject' => $subject,
                'entered' => (int) ($entered["{$division->id}:{$subject->id}"]->entered ?? 0),
            ]),
        ]);

        return view('admin.exams.show', compact('exam', 'matrix'));
    }

    public function togglePublish(Exam $exam): RedirectResponse
    {
        $exam->update(['results_published_at' => $exam->isPublished() ? null : now()]);

        return back()->with('success', $exam->isPublished()
            ? "{$exam->name} results published. Students and parents can now see them."
            : "{$exam->name} results unpublished. Marks can be edited again.");
    }

    public function results(Request $request, Exam $exam, Divisions $division): View|StreamedResponse
    {
        $division->load('class');
        $results = $this->results->forDivision($exam, $division);

        if ($request->query('format') === 'csv') {
            return $this->csv($exam, $division, $results);
        }

        return view('admin.exams.results', compact('exam', 'division') + $results);
    }

    public function reportCard(Exam $exam, Students $student): View
    {
        return view('exams.report-card', $this->results->reportCard($exam, $student) + ['backUrl' => url()->previous()]);
    }

    /**
     * @param  array{subjects: \Illuminate\Support\Collection, rows: \Illuminate\Support\Collection}  $results
     */
    protected function csv(Exam $exam, Divisions $division, array $results): StreamedResponse
    {
        $filename = 'results-'.str($exam->name)->slug().'-'.str($division->label)->slug().'.csv';

        return response()->streamDownload(function () use ($results) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student', ...$results['subjects']->pluck('subject_name'), 'Total', 'Percent', 'Grade', 'Result', 'Rank']);

            foreach ($results['rows'] as $row) {
                $cells = [$row['student']->first_name.' '.$row['student']->last_name];

                foreach ($results['subjects'] as $subject) {
                    $mark = $row['marks'][$subject->id] ?? null;
                    $cells[] = $mark ? ($mark->is_absent ? 'AB' : $mark->marks) : '';
                }

                fputcsv($out, [...$cells, $row['total'], $row['percent'] ?? '', $row['grade'], $row['result'], $row['rank'] ?? '']);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
