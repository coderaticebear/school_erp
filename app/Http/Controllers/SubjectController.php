<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubjectRequest;
use App\Models\Mark;
use App\Models\Subjects;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(): View
    {
        $subjects = Subjects::query()
            ->with('teachers')
            ->orderBy('subject_name')
            ->get();

        return view('subject.list', compact('subjects'));
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        Subjects::create($request->validated());

        return back()->with('success', 'Subject added.');
    }

    public function update(SubjectRequest $request, Subjects $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return back()->with('success', 'Subject updated.');
    }

    public function destroy(Subjects $subject): RedirectResponse
    {
        if ($subject->teachers()->exists()) {
            return back()->with('error', "{$subject->subject_name} is still taught by teachers. Remove it from those teachers first.");
        }

        if ($subject->timetableEntries()->exists() || Mark::query()->where('subject_id', $subject->id)->exists()) {
            return back()->with('error', "{$subject->subject_name} is used in the timetable or has marks and cannot be deleted.");
        }

        $subject->delete();

        return back()->with('success', 'Subject deleted.');
    }
}
