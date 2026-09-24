{{--
    Weekly timetable grid.
    $grid: ['days' => [day => name], 'periods' => Collection<Period>, 'cells' => [day][period_id] => TimetableEntry]
    $mode: 'division' shows the teacher in each slot, 'teacher' shows the division.
    $editable: slots open the edit dialog (admin division view only).
--}}
@php($editable = $editable ?? false)
@php($mode = $mode ?? 'division')

@if (! $grid || $grid['periods']->isEmpty())
    <div class="alert alert-light mb-0">No bell schedule is set up yet.</div>
@else
    <div class="table-responsive timetable-grid">
        <table class="table table-bordered mb-0">
            <thead class="thead-light">
                <tr>
                    <th class="time-col">Time</th>
                    @foreach ($grid['days'] as $dayName)
                        <th>{{ $dayName }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($grid['periods'] as $period)
                    <tr>
                        <td class="time-col">
                            {{ $period->label }}
                            <div class="small text-muted font-weight-normal">{{ $period->time_range }}</div>
                        </td>
                        @if ($period->is_break)
                            <td colspan="{{ count($grid['days']) }}">
                                <div class="slot break-slot">{{ $period->label }}</div>
                            </td>
                        @else
                            @foreach ($grid['days'] as $day => $dayName)
                                @php($entry = $grid['cells'][$day][$period->id] ?? null)
                                <td>
                                    <div @class([
                                            'slot',
                                            \App\Services\TimetableGrid::colorFor($entry->subject_id ?? 0) => $entry,
                                            'bg-soft-gray empty-slot' => ! $entry,
                                            'slot-editable' => $editable,
                                        ])
                                        @if ($editable)
                                            role="button"
                                            data-day="{{ $day }}"
                                            data-day-name="{{ $dayName }}"
                                            data-period="{{ $period->id }}"
                                            data-period-label="{{ $period->label }}"
                                            data-subject="{{ $entry?->subject_id }}"
                                            data-teacher="{{ $entry?->teacher_id }}"
                                        @endif>
                                        @if ($entry)
                                            <div class="slot-title">{{ $entry->subject->subject_name }}</div>
                                            <div class="slot-meta">{{ $mode === 'teacher' ? $entry->division->label : $entry->teacher->full_name }}</div>
                                        @else
                                            <div class="slot-title">Free</div>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
