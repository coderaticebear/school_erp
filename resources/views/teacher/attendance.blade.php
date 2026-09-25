@extends('adminlte::page')
@section('title', 'Attendance')

@section('content_header')
    <x-page-header title="Attendance" />
@stop

@section('content')
    @include('partials.alerts')

    @if (! $academicYear)
        <div class="alert alert-info">There is no active academic year.</div>
    @elseif (! $division)
        <div class="alert alert-info">You are not assigned to any division yet. Please contact the office.</div>
    @else
        <div class="row">
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-header">
                        <form method="get" action="{{ route('teacher.attendance') }}" class="form-inline">
                            <label class="mr-2" for="division">Division</label>
                            <select name="division" id="division" class="custom-select mr-3" onchange="this.form.submit()">
                                @foreach ($divisions as $option)
                                    <option value="{{ $option->id }}" @selected($option->id === $division->id)>{{ $option->label }}</option>
                                @endforeach
                            </select>
                            <label class="mr-2" for="date">Date</label>
                            <input type="date" name="date" id="date" class="form-control" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()">
                        </form>
                    </div>

                    @if (! $isSchoolDay)
                        <div class="card-body"><div class="alert alert-warning mb-0">{{ $date->format('l, M j') }} is not a school day.</div></div>
                    @elseif ($students->isEmpty())
                        <div class="card-body text-muted">No students are enrolled in {{ $division->label }}.</div>
                    @else
                        <form method="post" action="{{ route('teacher.attendance.store') }}">
                            @csrf
                            <input type="hidden" name="division_id" value="{{ $division->id }}">
                            <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                            <div class="card-body p-0">
                                <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
                                    <span class="text-muted small">
                                        @if ($existing->isNotEmpty())
                                            Already marked{{ $existing->first()->markedBy?->teacher ? ' by '.$existing->first()->markedBy->teacher->full_name : '' }} — saving updates it.
                                        @else
                                            Not marked yet. Everyone starts as present.
                                        @endif
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="allPresent">Mark All Present</button>
                                </div>
                                <div class="attendance-head d-none d-md-grid" aria-hidden="true">
                                    <span>Student</span><span>Status</span><span>Remark</span>
                                </div>
                                <ul class="attendance-list list-unstyled mb-0">
                                    @foreach ($students as $student)
                                        @php($record = $existing[$student->id] ?? null)
                                        @php($current = old("attendance.{$student->id}.status", $record?->status ?? \App\Models\Attendance::PRESENT))
                                        @php($remark = old("attendance.{$student->id}.remark", $record?->remark))
                                        @php($fullName = $student->first_name.' '.$student->last_name)
                                        <li class="attendance-row">
                                            <div class="attendance-name">{{ $fullName }}</div>
                                            <div class="btn-group btn-group-toggle attendance-status" data-toggle="buttons" role="radiogroup" aria-label="Attendance for {{ $fullName }}">
                                                @foreach (\App\Models\Attendance::STATUSES as $status => $label)
                                                    <label @class(['btn', 'btn-outline-secondary', 'active' => $current === $status])>
                                                        <input type="radio" name="attendance[{{ $student->id }}][status]" value="{{ $status }}" @checked($current === $status)> {{ $label }}
                                                    </label>
                                                @endforeach
                                            </div>
                                            <div class="attendance-remark">
                                                <button type="button" class="btn btn-link btn-sm px-0 remark-toggle" @if (filled($remark)) hidden @endif aria-controls="remark-{{ $student->id }}">+ Add remark</button>
                                                <input type="text" id="remark-{{ $student->id }}" name="attendance[{{ $student->id }}][remark]" aria-label="Remark for {{ $fullName }}" class="form-control form-control-sm" maxlength="255" value="{{ $remark }}" @unless (filled($remark)) hidden @endunless>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="card-footer attendance-actions">
                                <span class="attendance-summary text-muted" role="status" aria-live="polite" id="attendance-summary"></span>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1" aria-hidden="true"></i> Save Attendance</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            <div class="col-lg-3">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Recently Marked</h2></div>
                    <ul class="list-group list-group-flush">
                        @forelse ($recent as $day)
                            <li class="list-group-item d-flex justify-content-between">
                                <a href="{{ route('teacher.attendance', ['division' => $division->id, 'date' => $day->date->toDateString()]) }}">{{ $day->date->format('D, M j') }}</a>
                                <span class="text-muted">{{ $day->attended }}/{{ $day->total }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">Nothing marked yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    @endif
@stop

@section('js')
    <script>
        $(function () {
            const labels = { present: 'present', late: 'late', absent: 'absent', excused: 'excused' };

            const updateSummary = () => {
                const counts = { present: 0, late: 0, absent: 0, excused: 0 };
                $('.attendance-status input[type=radio]:checked').each(function () { counts[this.value]++; });
                $('#attendance-summary').text(Object.keys(labels).map(key => counts[key] + ' ' + labels[key]).join(' · '));
            };

            $('#allPresent').on('click', function () {
                $('input[type=radio][value=present]').each(function () {
                    $(this).prop('checked', true).closest('.btn-group').find('label').removeClass('active');
                    $(this).closest('label').addClass('active');
                });
                updateSummary();
            });

            $('.attendance-status').on('change', 'input[type=radio]', updateSummary);

            $('.remark-toggle').on('click', function () {
                $(this).prop('hidden', true);
                $('#' + $(this).attr('aria-controls')).prop('hidden', false).trigger('focus');
            });

            updateSummary();
        });
    </script>
@stop
