<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassRequest;
use App\Http\Requests\DivisionRequest;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\Divisions;
use App\Models\Mark;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function index(): View
    {
        $academicYear = AcademicYear::current();

        $classes = Classes::query()
            ->with(['divisions' => fn ($query) => $query
                ->orderBy('division_name')
                ->withCount(['studentClasses as students_count' => fn ($enrolments) => $enrolments
                    ->where('academic_year_id', $academicYear?->id)])
                ->with(['teachers' => fn ($teachers) => $teachers->wherePivot('class_teacher', true)])])
            ->orderBy('class_name')
            ->get();

        return view('admin.classes.index', compact('classes', 'academicYear'));
    }

    public function store(ClassRequest $request): RedirectResponse
    {
        Classes::create($request->validated());

        return back()->with('success', 'Class added.');
    }

    public function update(ClassRequest $request, Classes $class): RedirectResponse
    {
        $class->update($request->validated());

        return back()->with('success', 'Class updated.');
    }

    public function destroy(Classes $class): RedirectResponse
    {
        if ($class->divisions->contains(fn (Divisions $division) => $this->hasHistory($division))) {
            return back()->with('error', "{$class->class_name} has students, attendance or marks and cannot be deleted.");
        }

        DB::transaction(function () use ($class) {
            foreach ($class->divisions as $division) {
                $division->teachers()->detach();
                $division->delete();
            }

            $class->delete();
        });

        return back()->with('success', 'Class deleted.');
    }

    public function storeDivision(DivisionRequest $request): RedirectResponse
    {
        Divisions::create($request->validated());

        return back()->with('success', 'Division added.');
    }

    public function updateDivision(DivisionRequest $request, Divisions $division): RedirectResponse
    {
        $division->update($request->validated());

        return back()->with('success', 'Division updated.');
    }

    public function destroyDivision(Divisions $division): RedirectResponse
    {
        if ($this->hasHistory($division)) {
            return back()->with('error', "{$division->label} has students, attendance or marks and cannot be deleted.");
        }

        DB::transaction(function () use ($division) {
            $division->teachers()->detach();
            $division->delete();
        });

        return back()->with('success', 'Division deleted.');
    }

    /**
     * Enrolments, attendance and marks all reference a division, so any of them blocks deletion.
     */
    protected function hasHistory(Divisions $division): bool
    {
        return $division->studentClasses()->exists()
            || Attendance::query()->where('division_id', $division->id)->exists()
            || Mark::query()->where('division_id', $division->id)->exists();
    }
}
