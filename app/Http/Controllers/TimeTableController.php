<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClearTimetableEntryRequest;
use App\Http\Requests\GenerateTimetableRequest;
use App\Http\Requests\TimetableEntryRequest;
use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Subjects;
use App\Models\Teachers;
use App\Models\TimetableEntry;
use App\Services\TimetableGenerator;
use App\Services\TimetableGrid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeTableController extends Controller
{
    public function __construct(protected TimetableGenerator $generator) {}

    /**
     * Timetable manager: one division's week (default) or one teacher's week.
     */
    public function index(Request $request): View
    {
        $academicYear = AcademicYear::current();
        $divisions = $this->divisions();
        $teachers = Teachers::query()->with('login')->orderBy('first_name')->orderBy('last_name')->get();

        $teacher = $request->filled('teacher') ? $teachers->firstWhere('id', (int) $request->query('teacher')) : null;
        $division = $teacher ? null : ($divisions->firstWhere('id', (int) $request->query('division')) ?? $divisions->first());

        $data = compact('academicYear', 'divisions', 'teachers', 'teacher', 'division');

        if (! $academicYear) {
            return view('admin.timetable', $data + ['grid' => null, 'summary' => null, 'editOptions' => null]);
        }

        $grid = $teacher
            ? TimetableGrid::forTeacher($academicYear, $teacher)
            : ($division ? TimetableGrid::forDivision($academicYear, $division) : null);

        return view('admin.timetable', $data + [
            'grid' => $grid,
            'summary' => $this->summary($academicYear, $divisions),
            'editOptions' => $division ? $this->editOptions($academicYear, $division) : null,
        ]);
    }

    /**
     * Generate and save the timetable for one division or all divisions.
     */
    public function generate(GenerateTimetableRequest $request): RedirectResponse
    {
        $academicYear = AcademicYear::current();

        if (! $academicYear) {
            return back()->with('error', 'Activate an academic year before generating a timetable.');
        }

        $divisionId = $request->validated('division_id');
        $targets = $divisionId ? $this->divisions()->where('id', (int) $divisionId)->values() : $this->divisions();

        $result = $this->generator->generate($academicYear, $targets);
        $this->generator->save($academicYear, $targets, $result['entries']);

        $placed = count($result['entries']);
        $missing = array_sum(array_map('array_sum', $result['unscheduled']));
        $scope = $divisionId ? $targets->first()->label : 'all divisions';

        $message = "Generated {$placed} lessons for {$scope}.";
        if ($missing > 0) {
            $message .= " {$missing} lessons could not be placed; see Unscheduled.";
        }

        return redirect()
            ->route('admin.timetable', $divisionId ? ['division' => $divisionId] : [])
            ->with($missing > 0 ? 'error' : 'success', $message)
            ->with('timetable_issues', $result['issues']);
    }

    /**
     * Set one slot of a division's timetable.
     */
    public function saveEntry(TimetableEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        TimetableEntry::updateOrCreate(
            [
                'academic_year_id' => $request->academicYear()->id,
                'division_id' => $validated['division_id'],
                'day' => $validated['day'],
                'period_id' => $validated['period_id'],
            ],
            ['subject_id' => $validated['subject_id'], 'teacher_id' => $validated['teacher_id']],
        );

        return redirect()
            ->route('admin.timetable', ['division' => $validated['division_id']])
            ->with('success', 'Lesson saved.');
    }

    /**
     * Clear one slot of a division's timetable.
     */
    public function clearEntry(ClearTimetableEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        TimetableEntry::query()
            ->where('academic_year_id', $request->academicYear()->id)
            ->where('division_id', $validated['division_id'])
            ->where('day', $validated['day'])
            ->where('period_id', $validated['period_id'])
            ->delete();

        return redirect()
            ->route('admin.timetable', ['division' => $validated['division_id']])
            ->with('success', 'Lesson cleared.');
    }

    /**
     * Make the active year's timetable visible to teachers, students and parents.
     */
    public function publish(): RedirectResponse
    {
        $academicYear = AcademicYear::current();

        if (! $academicYear) {
            return back()->with('error', 'There is no active academic year.');
        }

        $academicYear->update(['timetable_published_at' => now()]);

        return back()->with('success', 'Timetable published. Teachers, students and parents can now see it.');
    }

    /**
     * Download a division's or teacher's week as CSV.
     */
    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $academicYear = AcademicYear::current();

        if (! $academicYear) {
            return back()->with('error', 'There is no active academic year.');
        }

        if ($request->filled('teacher')) {
            $owner = Teachers::findOrFail((int) $request->query('teacher'));
            $grid = TimetableGrid::forTeacher($academicYear, $owner);
            $name = $owner->full_name;
            $describe = fn (TimetableEntry $entry) => "{$entry->subject->subject_name} ({$entry->division->label})";
        } else {
            $owner = Divisions::with('class')->findOrFail((int) $request->query('division'));
            $grid = TimetableGrid::forDivision($academicYear, $owner);
            $name = $owner->label;
            $describe = fn (TimetableEntry $entry) => "{$entry->subject->subject_name} ({$entry->teacher->full_name})";
        }

        $filename = 'timetable-'.str($name)->slug().'-'.$academicYear->year.'.csv';

        return response()->streamDownload(function () use ($grid, $describe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Period', 'Time', ...array_values($grid['days'])]);

            foreach ($grid['periods'] as $period) {
                $row = [$period->label, $period->time_range];

                foreach (array_keys($grid['days']) as $day) {
                    $entry = $grid['cells'][$day][$period->id] ?? null;
                    $row[] = $period->is_break ? $period->label : ($entry ? $describe($entry) : '');
                }

                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return Collection<int, Divisions>
     */
    protected function divisions(): Collection
    {
        return Divisions::query()
            ->with('class')
            ->get()
            ->sortBy(fn (Divisions $division) => [$division->class->class_name ?? '', $division->division_name])
            ->values();
    }

    /**
     * Counts for the side panel, and lessons still to place per division and subject.
     *
     * @param  Collection<int, Divisions>  $divisions
     * @return array{lessons: int, published_at: mixed, unscheduled: list<array{division: Divisions, subject: string, missing: int}>, attention: int}
     */
    protected function summary(AcademicYear $academicYear, Collection $divisions): array
    {
        $slotCount = count(config('school.days')) * \App\Models\Period::query()->teaching()->count();

        $placed = TimetableEntry::query()
            ->where('academic_year_id', $academicYear->id)
            ->selectRaw('division_id, subject_id, count(*) as lessons')
            ->groupBy('division_id', 'subject_id')
            ->get()
            ->groupBy('division_id');

        $subjectNames = Subjects::query()->pluck('subject_name', 'id');
        $divisions->loadMissing(['teachers.subjects', 'teachers.login']);
        $unscheduled = [];

        foreach ($divisions as $division) {
            $targets = $this->generator->targetsFor($division, $slotCount)['targets'];
            $counts = ($placed[$division->id] ?? collect())->pluck('lessons', 'subject_id');

            foreach ($targets as $subjectId => $target) {
                $missing = $target - (int) ($counts[$subjectId] ?? 0);

                if ($missing > 0) {
                    $unscheduled[] = ['division' => $division, 'subject' => $subjectNames[$subjectId] ?? '?', 'missing' => $missing];
                }
            }
        }

        return [
            'lessons' => (int) $placed->flatten()->sum('lessons'),
            'published_at' => $academicYear->timetable_published_at,
            'unscheduled' => $unscheduled,
            'attention' => $this->entriesNeedingAttention($academicYear),
        ];
    }

    /**
     * Saved lessons that no longer match the setup (teacher inactive, unassigned, or no longer teaching the subject).
     */
    protected function entriesNeedingAttention(AcademicYear $academicYear): int
    {
        return TimetableEntry::query()
            ->where('academic_year_id', $academicYear->id)
            ->where(fn ($query) => $query
                ->whereDoesntHave('teacher.login', fn ($login) => $login->where('is_active', true))
                ->orWhereNotExists(fn ($sub) => $sub->selectRaw('1')->from('teacher_division')
                    ->whereColumn('teacher_division.teacher_id', 'timetable_entries.teacher_id')
                    ->whereColumn('teacher_division.division_id', 'timetable_entries.division_id'))
                ->orWhereNotExists(fn ($sub) => $sub->selectRaw('1')->from('subject_teacher')
                    ->whereColumn('subject_teacher.teacher_id', 'timetable_entries.teacher_id')
                    ->whereColumn('subject_teacher.subject_id', 'timetable_entries.subject_id')))
            ->count();
    }

    /**
     * What the edit dialog may offer for a division: subjects with their eligible teachers,
     * and the slots where each teacher is already busy elsewhere.
     *
     * @return array{subjects: list<array{id: int, name: string, teachers: list<array{id: int, name: string}>}>, busy: array<int, list<string>>}
     */
    protected function editOptions(AcademicYear $academicYear, Divisions $division): array
    {
        $division->loadMissing(['teachers.subjects', 'teachers.login']);
        $teachers = $division->teachers->filter(fn ($teacher) => $teacher->login?->is_active);

        $subjects = $teachers
            ->flatMap(fn ($teacher) => $teacher->subjects->map(fn ($subject) => [$subject, $teacher]))
            ->groupBy(fn ($pair) => $pair[0]->id)
            ->map(fn ($pairs) => [
                'id' => $pairs[0][0]->id,
                'name' => $pairs[0][0]->subject_name,
                'teachers' => $pairs->map(fn ($pair) => ['id' => $pair[1]->id, 'name' => $pair[1]->full_name])->values()->all(),
            ])
            ->sortBy('name')
            ->values()
            ->all();

        $busy = TimetableEntry::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('division_id', '!=', $division->id)
            ->whereIn('teacher_id', $teachers->pluck('id'))
            ->get(['teacher_id', 'day', 'period_id'])
            ->groupBy('teacher_id')
            ->map(fn ($entries) => $entries->map(fn ($entry) => "{$entry->day}:{$entry->period_id}")->values()->all())
            ->all();

        return ['subjects' => $subjects, 'busy' => $busy];
    }
}
