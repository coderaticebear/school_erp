<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Period;
use App\Models\Teachers;
use App\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Day x period grids of timetable entries, for a division or a teacher.
 */
class TimetableGrid
{
    public const DAY_NAMES = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /**
     * @return array<int, string> ISO day number => day name, for the configured school week
     */
    public static function days(): array
    {
        return collect(config('school.days'))
            ->mapWithKeys(fn (int $day) => [$day => self::DAY_NAMES[$day]])
            ->all();
    }

    /**
     * @return array{days: array<int, string>, periods: Collection<int, Period>, cells: array<int, array<int, TimetableEntry>>}
     */
    public static function forDivision(AcademicYear $academicYear, Divisions $division): array
    {
        return self::build($academicYear, fn (Builder $query) => $query->where('division_id', $division->id));
    }

    /**
     * @return array{days: array<int, string>, periods: Collection<int, Period>, cells: array<int, array<int, TimetableEntry>>}
     */
    public static function forTeacher(AcademicYear $academicYear, Teachers $teacher): array
    {
        return self::build($academicYear, fn (Builder $query) => $query->where('teacher_id', $teacher->id));
    }

    /**
     * @param  callable(Builder): Builder  $scope
     * @return array{days: array<int, string>, periods: Collection<int, Period>, cells: array<int, array<int, TimetableEntry>>}
     */
    protected static function build(AcademicYear $academicYear, callable $scope): array
    {
        $entries = $scope(TimetableEntry::query()->where('academic_year_id', $academicYear->id))
            ->with(['subject', 'teacher', 'division.class'])
            ->get();

        $cells = [];
        foreach ($entries as $entry) {
            $cells[$entry->day][$entry->period_id] = $entry;
        }

        return [
            'days' => self::days(),
            'periods' => Period::query()->ordered()->get(),
            'cells' => $cells,
        ];
    }

    /**
     * A stable soft colour class per subject, matching the timetable CSS palette.
     */
    public static function colorFor(int $subjectId): string
    {
        $palette = ['bg-soft-blue', 'bg-soft-green', 'bg-soft-teal', 'bg-soft-orange', 'bg-soft-purple', 'bg-soft-pink', 'bg-soft-yellow', 'bg-soft-indigo'];

        return $palette[$subjectId % count($palette)];
    }
}
