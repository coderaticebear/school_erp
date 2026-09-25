@extends('adminlte::page')
@section('title', 'Timetable Manager')

@php($colorFor = fn (int $subjectId) => \App\Services\TimetableGrid::colorFor($subjectId))

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h1 class="mb-0">Timetable Manager</h1>
            <small class="text-muted">Build, review, and publish weekly schedules{{ $academicYear ? ' for '.$academicYear->year : '' }}</small>
        </div>
        @if ($academicYear && $divisions->isNotEmpty())
            <div class="mt-3 mt-sm-0 no-print">
                <div class="btn-group mr-2">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown">
                        <i class="fas fa-magic mr-1"></i> Generate
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        @if ($division)
                            <form action="{{ route('admin.timetable.generate') }}" method="post" onsubmit="return confirm('Replace the timetable of {{ $division->label }}?')">
                                @csrf
                                <input type="hidden" name="division_id" value="{{ $division->id }}">
                                <button type="submit" class="dropdown-item">Only {{ $division->label }}</button>
                            </form>
                        @endif
                        <form action="{{ route('admin.timetable.generate') }}" method="post" onsubmit="return confirm('Replace the timetables of ALL divisions?')">
                            @csrf
                            <button type="submit" class="dropdown-item">All Divisions</button>
                        </form>
                    </div>
                </div>
                @if ($division || $teacher)
                    <a href="{{ route('admin.timetable.export', $teacher ? ['teacher' => $teacher->id] : ['division' => $division->id]) }}" class="btn btn-outline-secondary mr-2">
                        <i class="fas fa-file-export mr-1"></i> Export
                    </a>
                @endif
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
            </div>
        @endif
    </div>
@stop

@section('content')
    <div class="no-print">
        @include('partials.alerts')

        @if (session('timetable_issues'))
            <div class="alert alert-warning">
                <ul class="mb-0">
                    @foreach (session('timetable_issues') as $issue)
                        <li>{{ $issue }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    @if (! $academicYear)
        <div class="alert alert-warning">There is no active academic year. <a href="{{ route('admin.academic-years.index') }}">Activate one</a> to build a timetable.</div>
    @elseif ($divisions->isEmpty())
        <div class="alert alert-info">There are no divisions yet. <a href="{{ route('admin.classes.index') }}">Add classes and divisions</a> first.</div>
    @else
        <div class="row">
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-header border-0">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <h2 class="card-title mb-2 mb-sm-0">
                                <i class="fas fa-calendar-alt mr-2 text-primary"></i>
                                {{ $teacher ? $teacher->full_name : $division->label }}
                            </h2>
                            <div class="text-muted small">
                                @if ($summary['published_at'])
                                    Published {{ $summary['published_at']->diffForHumans() }}
                                @else
                                    Not published yet
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form class="row no-print" id="timetable-filters" method="get" action="{{ route('admin.timetable') }}">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="division-select">Division</label>
                                    <select class="custom-select" id="division-select" name="division" onchange="this.form.teacher.value=''; this.form.submit()">
                                        @foreach ($divisions as $option)
                                            <option value="{{ $option->id }}" @selected($division?->id === $option->id)>{{ $option->label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="teacher-select">Teacher</label>
                                    <select class="custom-select" id="teacher-select" name="teacher" onchange="this.form.division.disabled = this.value !== ''; this.form.submit()">
                                        <option value="">— Show a division —</option>
                                        @foreach ($teachers as $option)
                                            <option value="{{ $option->id }}" @selected($teacher?->id === $option->id)>{{ $option->full_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </form>

                        @include('timetable.grid', ['grid' => $grid, 'mode' => $teacher ? 'teacher' : 'division', 'editable' => (bool) $division])

                        @if ($division)
                            <p class="text-muted small mt-2 mb-0 no-print"><i class="fas fa-info-circle mr-1"></i> Click a slot to change or clear it.</p>
                        @endif
                    </div>
                    <div class="card-footer d-flex flex-wrap align-items-center justify-content-between no-print">
                        <div class="text-muted small">
                            @if ($summary['published_at'])
                                Last published: {{ $summary['published_at']->format('M j, Y g:i A') }} • changes are visible immediately
                            @else
                                Teachers, students and parents cannot see the timetable until you publish it.
                            @endif
                        </div>
                        <form action="{{ route('admin.timetable.publish') }}" method="post">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-upload mr-1"></i> {{ $summary['published_at'] ? 'Publish Again' : 'Publish Timetable' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 no-print">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i class="fas fa-sliders-h mr-2"></i>Quick Stats</h2>
                    </div>
                    <div class="card-body">
                        <div class="info-box bg-light mb-3">
                            <span class="info-box-icon bg-primary"><i class="fas fa-school"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Divisions</span>
                                <span class="info-box-number">{{ $divisions->count() }}</span>
                            </div>
                        </div>
                        <div class="info-box bg-light mb-3">
                            <span class="info-box-icon bg-success"><i class="fas fa-chalkboard-teacher"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Lessons Scheduled</span>
                                <span class="info-box-number">{{ $summary['lessons'] }}</span>
                            </div>
                        </div>
                        <div class="info-box bg-light mb-0">
                            <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text" title="Lessons whose teacher is inactive, no longer assigned to the division, or no longer teaches the subject">Needs Attention</span>
                                <span class="info-box-number">{{ $summary['attention'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i class="fas fa-list mr-2"></i>Unscheduled</h2>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @forelse (collect($summary['unscheduled'])->take(12) as $item)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="{{ route('admin.timetable', ['division' => $item['division']->id]) }}">{{ $item['subject'] }} - {{ $item['division']->label }}</a>
                                    <span class="badge badge-warning">{{ $item['missing'] }} {{ Str::plural('slot', $item['missing']) }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">Every subject has all its lessons.</li>
                            @endforelse
                            @if (count($summary['unscheduled']) > 12)
                                <li class="list-group-item text-muted small">and {{ count($summary['unscheduled']) - 12 }} more…</li>
                            @endif
                        </ul>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('admin.periods.index') }}" class="btn btn-outline-primary btn-block"><i class="fas fa-bell mr-1"></i> Bell Schedule</a>
                    </div>
                </div>

                @php($legend = collect($grid['cells'] ?? [])->flatten()->unique('subject_id')->sortBy('subject.subject_name'))
                @if ($legend->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title"><i class="fas fa-tags mr-2"></i>Legend</h2>
                        </div>
                        <div class="card-body">
                            @foreach ($legend as $entry)
                                <div class="legend-item"><span class="legend-swatch {{ $colorFor($entry->subject_id) }}"></span>{{ $entry->subject->subject_name }}</div>
                            @endforeach
                            <div class="legend-item"><span class="legend-swatch bg-soft-gray"></span>Free / Break</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($division)
            <div class="modal fade" id="slotModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form action="{{ route('admin.timetable.entries.save') }}" method="post" id="slotForm">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="division_id" value="{{ $division->id }}">
                            <input type="hidden" name="day" id="slotDay">
                            <input type="hidden" name="period_id" id="slotPeriod">
                            <div class="modal-header">
                                <h5 class="modal-title" id="slotTitle"></h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                @if (empty($editOptions['subjects']))
                                    <p class="text-muted mb-0">No active teachers are assigned to {{ $division->label }}. <a href="{{ route('admin.divisions.teachers.edit', $division) }}">Assign teachers</a> first.</p>
                                @else
                                    <div class="form-group">
                                        <label for="slotSubject">Subject</label>
                                        <select name="subject_id" id="slotSubject" class="custom-select" required>
                                            @foreach ($editOptions['subjects'] as $subject)
                                                <option value="{{ $subject['id'] }}">{{ $subject['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="slotTeacher">Teacher</label>
                                        <select name="teacher_id" id="slotTeacher" class="custom-select" required></select>
                                    </div>
                                @endif
                            </div>
                        </form>
                        <div class="modal-footer justify-content-between">
                            <form action="{{ route('admin.timetable.entries.clear') }}" method="post" id="clearForm">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="division_id" value="{{ $division->id }}">
                                <input type="hidden" name="day" id="clearDay">
                                <input type="hidden" name="period_id" id="clearPeriod">
                                <button type="submit" class="btn btn-outline-danger" id="clearButton">Clear Slot</button>
                            </form>
                            <div>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                @unless (empty($editOptions['subjects']))
                                    <button type="submit" form="slotForm" class="btn btn-primary">Save</button>
                                @endunless
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
@stop

@include('timetable.styles')

@if ($division && $editOptions)
    @section('js')
        <script>
            $(function () {
                const options = @json($editOptions);
                const bySubject = Object.fromEntries(options.subjects.map(s => [s.id, s.teachers]));
                let slot = null;

                const fillTeachers = (subjectId, selectedTeacherId) => {
                    const $teacher = $('#slotTeacher').empty();
                    (bySubject[subjectId] || []).forEach(t => {
                        const busy = (options.busy[t.id] || []).includes(slot.day + ':' + slot.period);
                        $('<option>', { value: t.id, text: t.name + (busy ? ' (busy in another class)' : ''), disabled: busy })
                            .prop('selected', t.id === selectedTeacherId)
                            .appendTo($teacher);
                    });
                    if (! $teacher.val()) {
                        $teacher.find('option:not(:disabled)').first().prop('selected', true);
                    }
                };

                $('.slot-editable').on('click', function () {
                    slot = $(this).data();
                    $('#slotTitle').text(slot.dayName + ', ' + slot.periodLabel);
                    $('#slotDay, #clearDay').val(slot.day);
                    $('#slotPeriod, #clearPeriod').val(slot.period);
                    $('#clearButton').toggle(!! slot.subject);
                    if (slot.subject) {
                        $('#slotSubject').val(slot.subject);
                    }
                    fillTeachers(Number($('#slotSubject').val()), slot.teacher || null);
                    $('#slotModal').modal('show');
                });

                $('#slotSubject').on('change', function () {
                    fillTeachers(Number(this.value), null);
                });
            });
        </script>
    @stop
@endif
