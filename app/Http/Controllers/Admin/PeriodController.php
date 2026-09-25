<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PeriodRequest;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PeriodController extends Controller
{
    public function index(): View
    {
        $periods = Period::query()->ordered()->withCount('entries')->get();

        return view('admin.periods.index', compact('periods'));
    }

    public function store(PeriodRequest $request): RedirectResponse
    {
        Period::create($request->validated());

        return back()->with('success', 'Period added.');
    }

    public function update(PeriodRequest $request, Period $period): RedirectResponse
    {
        if ($request->validated('is_break') && $period->entries()->exists()) {
            return back()->with('error', "{$period->label} has lessons scheduled. Clear them before turning it into a break.");
        }

        $period->update($request->validated());

        return back()->with('success', 'Period updated.');
    }

    public function destroy(Period $period): RedirectResponse
    {
        if ($period->entries()->exists()) {
            return back()->with('error', "{$period->label} has lessons scheduled and cannot be deleted.");
        }

        $period->delete();

        return back()->with('success', 'Period deleted.');
    }
}
