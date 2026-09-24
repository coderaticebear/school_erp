<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DivisionTeachersRequest;
use App\Models\Divisions;
use App\Models\Teachers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DivisionTeacherController extends Controller
{
    public function edit(Divisions $division): View
    {
        $division->load(['class', 'teachers']);

        $teachers = Teachers::query()
            ->with(['subjects', 'login'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('admin.divisions.teachers', compact('division', 'teachers'));
    }

    /**
     * Replace the division's teachers and class teacher.
     */
    public function update(DivisionTeachersRequest $request, Divisions $division): RedirectResponse
    {
        $classTeacherId = (int) $request->validated('class_teacher_id');

        $sync = collect($request->validated('teacher_ids') ?? [])
            ->mapWithKeys(fn ($teacherId) => [(int) $teacherId => ['class_teacher' => (int) $teacherId === $classTeacherId]])
            ->all();

        DB::transaction(function () use ($division, $sync) {
            // Clear the flag first so switching class teachers never breaks the one-per-division index.
            $division->teachers()->newPivotStatement()
                ->where('division_id', $division->id)
                ->update(['class_teacher' => false]);

            $division->teachers()->sync($sync);
        });

        return redirect()
            ->route('admin.classes.index')
            ->with('success', "Teachers for {$division->label} saved.");
    }
}
