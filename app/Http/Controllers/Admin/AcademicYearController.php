<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicYearRequest;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\TimetableEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function index(): View
    {
        $academicYears = AcademicYear::query()
            ->withCount('studentClass')
            ->orderByDesc('year')
            ->get();

        return view('admin.academic-years.index', compact('academicYears'));
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        AcademicYear::create([
            'year' => $request->validated('year'),
            'is_active' => ! AcademicYear::query()->where('is_active', true)->exists(),
        ]);

        return back()->with('success', 'Academic year added.');
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->update(['year' => $request->validated('year')]);

        return back()->with('success', 'Academic year updated.');
    }

    /**
     * Make this the only active academic year.
     */
    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        DB::transaction(function () use ($academicYear) {
            AcademicYear::query()->where('id', '!=', $academicYear->id)->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
        });

        return back()->with('success', "{$academicYear->year} is now the active academic year.");
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->is_active) {
            return back()->with('error', 'The active academic year cannot be deleted.');
        }

        $inUse = $academicYear->studentClass()->exists()
            || Exam::query()->where('academic_year_id', $academicYear->id)->exists()
            || Attendance::query()->where('academic_year_id', $academicYear->id)->exists()
            || TimetableEntry::query()->where('academic_year_id', $academicYear->id)->exists();

        if ($inUse) {
            return back()->with('error', 'This academic year has enrolments, exams, attendance or a timetable and cannot be deleted.');
        }

        $academicYear->delete();

        return back()->with('success', 'Academic year deleted.');
    }
}
