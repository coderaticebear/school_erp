<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Period;
use App\Models\Subjects;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Builds weekly timetables for divisions.
 *
 * Rules:
 * - A division only gets subjects taught by its assigned, active teachers.
 * - Each (division, subject) is taught by one teacher all week.
 * - A teacher is never in two places in the same slot, including slots already
 *   used by divisions that are not being regenerated.
 * - A subject appears at most `school.timetable.max_subject_periods_per_day`
 *   times a day in a division (relaxed by one only to place leftover lessons).
 * - Weekly counts follow Subjects::periods_per_week; subjects without a value
 *   share the remaining periods evenly.
 */
class TimetableGenerator
{
    /**
     * Weekly lesson targets per subject for a division.
     *
     * @return array{targets: array<int, int>, issues: list<string>}
     */
    public function targetsFor(Divisions $division, int $slotCount): array
    {
        $subjects = $this->teachableSubjects($division);

        if ($subjects->isEmpty()) {
            return ['targets' => [], 'issues' => []];
        }

        $issues = [];
        $explicit = $subjects->filter(fn (Subjects $subject) => $subject->periods_per_week !== null);
        $implicit = $subjects->reject(fn (Subjects $subject) => $subject->periods_per_week !== null)->values();

        $targets = $explicit->mapWithKeys(fn (Subjects $subject) => [$subject->id => $subject->periods_per_week])->all();
        $explicitTotal = array_sum($targets);

        if ($explicitTotal > $slotCount) {
            $issues[] = "{$division->label}: subjects ask for {$explicitTotal} periods a week but only {$slotCount} exist. Extra lessons cannot be placed.";
        }

        $remaining = max(0, $slotCount - $explicitTotal);

        if ($implicit->isNotEmpty()) {
            $base = intdiv($remaining, $implicit->count());
            $extra = $remaining % $implicit->count();

            foreach ($implicit as $index => $subject) {
                $targets[$subject->id] = $base + ($index < $extra ? 1 : 0);
            }
        }

        return ['targets' => array_filter($targets), 'issues' => $issues];
    }

    /**
     * Generate (but do not save) timetables for the given divisions.
     *
     * @param  Collection<int, Divisions>  $divisions
     * @return array{
     *     entries: list<array{academic_year_id: int, division_id: int, day: int, period_id: int, subject_id: int, teacher_id: int}>,
     *     unscheduled: array<int, array<int, int>>,
     *     issues: list<string>
     * }
     */
    public function generate(AcademicYear $academicYear, Collection $divisions, ?int $seed = null): array
    {
        $days = config('school.days');
        $periodIds = Period::query()->teaching()->ordered()->pluck('id')->all();
        $slotCount = count($days) * count($periodIds);
        $maxPerDay = max(1, (int) config('school.timetable.max_subject_periods_per_day'));
        $attempts = max(1, (int) config('school.timetable.attempts'));
        $seed ??= random_int(1, PHP_INT_MAX >> 1);

        $divisions->loadMissing(['class', 'teachers.subjects', 'teachers.login']);

        $issues = [];
        $plans = [];

        if ($slotCount === 0) {
            return ['entries' => [], 'unscheduled' => [], 'issues' => ['There are no teaching periods or school days set up.']];
        }

        foreach ($divisions as $division) {
            $candidates = $this->candidateTeachers($division);

            if ($candidates === []) {
                $issues[] = "{$division->label}: no active teachers are assigned, so it was left empty.";

                continue;
            }

            ['targets' => $targets, 'issues' => $targetIssues] = $this->targetsFor($division, $slotCount);
            array_push($issues, ...$targetIssues);

            $plans[$division->id] = ['candidates' => $candidates, 'targets' => $targets];
        }

        $baseBusy = $this->busySlotsOutside($academicYear, $divisions->pluck('id')->all());

        $best = null;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $result = $this->attempt($plans, $days, $periodIds, $baseBusy, $maxPerDay, $slotCount, new Randomizer(new Mt19937($seed + $attempt)));

            if ($best === null || $result['missing'] < $best['missing']) {
                $best = $result;
            }

            if ($best['missing'] === 0) {
                break;
            }
        }

        $entries = array_map(fn (array $entry) => ['academic_year_id' => $academicYear->id, ...$entry], $best['entries'] ?? []);

        return [
            'entries' => $entries,
            'unscheduled' => $best['unscheduled'] ?? [],
            'issues' => $issues,
        ];
    }

    /**
     * Replace the saved timetable of the given divisions with generated entries.
     *
     * @param  Collection<int, Divisions>  $divisions
     * @param  list<array<string, int>>  $entries
     */
    public function save(AcademicYear $academicYear, Collection $divisions, array $entries): void
    {
        DB::transaction(function () use ($academicYear, $divisions, $entries) {
            TimetableEntry::query()
                ->where('academic_year_id', $academicYear->id)
                ->whereIn('division_id', $divisions->pluck('id'))
                ->delete();

            $now = now();

            foreach (array_chunk($entries, 500) as $chunk) {
                TimetableEntry::insert(array_map(fn (array $entry) => [...$entry, 'created_at' => $now, 'updated_at' => $now], $chunk));
            }
        });
    }

    /**
     * Subjects a division can be taught: those its active assigned teachers teach.
     *
     * @return Collection<int, Subjects>
     */
    public function teachableSubjects(Divisions $division): Collection
    {
        $division->loadMissing(['teachers.subjects', 'teachers.login']);

        return $division->teachers
            ->filter(fn ($teacher) => $teacher->login?->is_active)
            ->flatMap(fn ($teacher) => $teacher->subjects)
            ->unique('id')
            ->sortBy('subject_name')
            ->values();
    }

    /**
     * @return array<int, list<int>> subject id => teacher ids
     */
    protected function candidateTeachers(Divisions $division): array
    {
        $candidates = [];

        foreach ($division->teachers as $teacher) {
            if (! $teacher->login?->is_active) {
                continue;
            }

            foreach ($teacher->subjects as $subject) {
                $candidates[$subject->id][] = $teacher->id;
            }
        }

        return $candidates;
    }

    /**
     * Teacher slots already used by divisions that are not being regenerated.
     *
     * @param  list<int>  $divisionIds
     * @return array<string, true>
     */
    protected function busySlotsOutside(AcademicYear $academicYear, array $divisionIds): array
    {
        return TimetableEntry::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereNotIn('division_id', $divisionIds)
            ->get(['teacher_id', 'day', 'period_id'])
            ->mapWithKeys(fn (TimetableEntry $entry) => [$this->slotKey($entry->teacher_id, $entry->day, $entry->period_id) => true])
            ->all();
    }

    /**
     * One randomised scheduling pass over every slot of every division, then a swap repair.
     *
     * @param  array<int, array{candidates: array<int, list<int>>, targets: array<int, int>}>  $plans
     * @param  list<int>  $days
     * @param  list<int>  $periodIds
     * @param  array<string, true>  $busy
     * @return array{entries: list<array<string, int>>, unscheduled: array<int, array<int, int>>, missing: int}
     */
    protected function attempt(array $plans, array $days, array $periodIds, array $busy, int $maxPerDay, int $slotCount, Randomizer $random): array
    {
        $teacherFor = $this->chooseTeachers($plans, $slotCount, $random);

        $remaining = [];
        $perDay = [];
        $grid = []; // division => day => period => [subject id, teacher id]

        foreach ($plans as $divisionId => $plan) {
            foreach ($plan['targets'] as $subjectId => $target) {
                if (isset($teacherFor[$divisionId][$subjectId])) {
                    $remaining[$divisionId][$subjectId] = $target;
                }
            }
        }

        $divisionIds = array_keys($plans);

        // First pass keeps the daily cap; the second relaxes it by one for leftovers.
        foreach ([$maxPerDay, $maxPerDay + 1] as $dailyCap) {
            foreach ($days as $day) {
                foreach ($periodIds as $periodId) {
                    foreach ($random->shuffleArray($divisionIds) as $divisionId) {
                        if (isset($grid[$divisionId][$day][$periodId])) {
                            continue;
                        }

                        $subjectId = $this->pickSubject(
                            $remaining[$divisionId] ?? [],
                            $perDay[$divisionId][$day] ?? [],
                            $teacherFor[$divisionId] ?? [],
                            $busy, $day, $periodId, $dailyCap, $random,
                        );

                        if ($subjectId !== null) {
                            $teacherId = $teacherFor[$divisionId][$subjectId];
                            $grid[$divisionId][$day][$periodId] = [$subjectId, $teacherId];
                            $busy[$this->slotKey($teacherId, $day, $periodId)] = true;
                            $remaining[$divisionId][$subjectId]--;
                            $perDay[$divisionId][$day][$subjectId] = ($perDay[$divisionId][$day][$subjectId] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        $this->repairBySwapping($grid, $remaining, $perDay, $busy, $teacherFor, $days, $periodIds, $maxPerDay + 1);

        $entries = [];
        foreach ($grid as $divisionId => $byDay) {
            foreach ($byDay as $day => $byPeriod) {
                foreach ($byPeriod as $periodId => [$subjectId, $teacherId]) {
                    $entries[] = [
                        'division_id' => $divisionId,
                        'day' => $day,
                        'period_id' => $periodId,
                        'subject_id' => $subjectId,
                        'teacher_id' => $teacherId,
                    ];
                }
            }
        }

        $unscheduled = [];
        foreach ($remaining as $divisionId => $subjects) {
            foreach ($subjects as $subjectId => $left) {
                if ($left > 0) {
                    $unscheduled[$divisionId][$subjectId] = $left;
                }
            }
        }

        return [
            'entries' => $entries,
            'unscheduled' => $unscheduled,
            'missing' => array_sum(array_map('array_sum', $unscheduled)),
        ];
    }

    /**
     * Place leftover lessons by moving an existing lesson of the same division into
     * an empty slot, freeing a slot where the leftover subject's teacher is available.
     *
     * @param  array<int, array<int, array<int, array{0: int, 1: int}>>>  $grid
     * @param  array<int, array<int, int>>  $remaining
     * @param  array<int, array<int, array<int, int>>>  $perDay
     * @param  array<string, true>  $busy
     * @param  array<int, array<int, int>>  $teacherFor
     * @param  list<int>  $days
     * @param  list<int>  $periodIds
     */
    protected function repairBySwapping(array &$grid, array &$remaining, array &$perDay, array &$busy, array $teacherFor, array $days, array $periodIds, int $dailyCap): void
    {
        foreach ($remaining as $divisionId => $subjects) {
            foreach ($subjects as $subjectId => $left) {
                $teacherId = $teacherFor[$divisionId][$subjectId];

                while ($remaining[$divisionId][$subjectId] > 0) {
                    $placed = false;

                    foreach ($days as $emptyDay) {
                        foreach ($periodIds as $emptyPeriod) {
                            if (isset($grid[$divisionId][$emptyDay][$emptyPeriod])) {
                                continue;
                            }

                            // Find a filled slot X whose lesson can move to the empty slot E, and where our teacher is free.
                            foreach ($grid[$divisionId] ?? [] as $day => $byPeriod) {
                                foreach ($byPeriod as $periodId => [$movingSubject, $movingTeacher]) {
                                    if ($movingSubject === $subjectId
                                        || isset($busy[$this->slotKey($teacherId, $day, $periodId)])
                                        || isset($busy[$this->slotKey($movingTeacher, $emptyDay, $emptyPeriod)])
                                        || ($perDay[$divisionId][$day][$subjectId] ?? 0) >= $dailyCap
                                        || ($day !== $emptyDay && ($perDay[$divisionId][$emptyDay][$movingSubject] ?? 0) >= $dailyCap)) {
                                        continue;
                                    }

                                    // Move the existing lesson X -> E.
                                    unset($busy[$this->slotKey($movingTeacher, $day, $periodId)]);
                                    $busy[$this->slotKey($movingTeacher, $emptyDay, $emptyPeriod)] = true;
                                    $grid[$divisionId][$emptyDay][$emptyPeriod] = [$movingSubject, $movingTeacher];
                                    $perDay[$divisionId][$day][$movingSubject]--;
                                    $perDay[$divisionId][$emptyDay][$movingSubject] = ($perDay[$divisionId][$emptyDay][$movingSubject] ?? 0) + 1;

                                    // Put the leftover lesson in X.
                                    $busy[$this->slotKey($teacherId, $day, $periodId)] = true;
                                    $grid[$divisionId][$day][$periodId] = [$subjectId, $teacherId];
                                    $perDay[$divisionId][$day][$subjectId] = ($perDay[$divisionId][$day][$subjectId] ?? 0) + 1;
                                    $remaining[$divisionId][$subjectId]--;

                                    $placed = true;
                                    break 4;
                                }
                            }
                        }
                    }

                    if (! $placed) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Give each (division, subject) one teacher, spreading lessons over the least-loaded teachers.
     *
     * @param  array<int, array{candidates: array<int, list<int>>, targets: array<int, int>}>  $plans
     * @return array<int, array<int, int>> division id => subject id => teacher id
     */
    protected function chooseTeachers(array $plans, int $slotCount, Randomizer $random): array
    {
        $load = [];
        $choices = [];

        $pairs = [];
        foreach ($plans as $divisionId => $plan) {
            foreach ($plan['targets'] as $subjectId => $target) {
                $pairs[] = [$divisionId, $subjectId, $target];
            }
        }

        // Subjects with the fewest possible teachers are decided first.
        $pairs = $random->shuffleArray($pairs);
        usort($pairs, fn ($a, $b) => count($plans[$a[0]]['candidates'][$a[1]] ?? []) <=> count($plans[$b[0]]['candidates'][$b[1]] ?? []));

        foreach ($pairs as [$divisionId, $subjectId, $target]) {
            $candidates = $plans[$divisionId]['candidates'][$subjectId] ?? [];

            if ($candidates === []) {
                continue;
            }

            $candidates = $random->shuffleArray($candidates);
            usort($candidates, function (int $a, int $b) use ($load, $target, $slotCount) {
                $overA = ($load[$a] ?? 0) + $target > $slotCount;
                $overB = ($load[$b] ?? 0) + $target > $slotCount;

                return [$overA, $load[$a] ?? 0] <=> [$overB, $load[$b] ?? 0];
            });

            $teacherId = $candidates[0];
            $choices[$divisionId][$subjectId] = $teacherId;
            $load[$teacherId] = ($load[$teacherId] ?? 0) + $target;
        }

        return $choices;
    }

    /**
     * The subject with the most lessons left whose teacher is free in this slot.
     *
     * @param  array<int, int>  $remaining
     * @param  array<int, int>  $todayCounts
     * @param  array<int, int>  $teacherFor
     * @param  array<string, true>  $busy
     */
    protected function pickSubject(array $remaining, array $todayCounts, array $teacherFor, array $busy, int $day, int $periodId, int $dailyCap, Randomizer $random): ?int
    {
        $options = [];

        foreach ($remaining as $subjectId => $left) {
            if ($left <= 0 || ($todayCounts[$subjectId] ?? 0) >= $dailyCap) {
                continue;
            }

            if (isset($busy[$this->slotKey($teacherFor[$subjectId], $day, $periodId)])) {
                continue;
            }

            $options[] = [$subjectId, $left, $random->getInt(0, 1000)];
        }

        if ($options === []) {
            return null;
        }

        usort($options, fn ($a, $b) => [$b[1], $b[2]] <=> [$a[1], $a[2]]);

        return $options[0][0];
    }

    protected function slotKey(int $teacherId, int $day, int $periodId): string
    {
        return "{$teacherId}:{$day}:{$periodId}";
    }
}
