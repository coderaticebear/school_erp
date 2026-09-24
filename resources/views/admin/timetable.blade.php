@extends('adminlte::page')
@section('title', 'Timetable Manager')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h1 class="mb-0">Timetable Manager</h1>
            <small class="text-muted">Build, review, and publish weekly schedules</small>
        </div>
        <div class="mt-3 mt-sm-0">
            <button class="btn btn-primary mr-2">
                <i class="fas fa-magic mr-1"></i> Generate
            </button>
            <button class="btn btn-outline-secondary mr-2">
                <i class="fas fa-file-export mr-1"></i> Export
            </button>
            <button class="btn btn-outline-secondary">
                <i class="fas fa-print mr-1"></i> Print
            </button>
        </div>
    </div>
@stop

@section('content')
    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header border-0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <h3 class="card-title mb-2 mb-sm-0">
                            <i class="fas fa-calendar-alt mr-2 text-primary"></i>
                            Weekly Timetable
                        </h3>
                        <div class="text-muted small">
                            Week of <strong>Feb 8, 2026</strong>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form class="row" id="timetable-filters">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Division</label>
                                <select class="custom-select" id="division-select">
                                    <option selected value="">All Divisions</option>
                                    @if(!empty($divisions))
                                        @foreach($divisions as $division)
                                            @php
                                                $divisionLabel = ($division->class->class_name ?? 'Class') . ' - ' . $division->division_name;
                                            @endphp
                                            <option value="{{ $division->id }}">{{ $divisionLabel }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Room</label>
                                <select class="custom-select">
                                    <option selected>All Rooms</option>
                                    <option>Room 101</option>
                                    <option>Room 102</option>
                                    <option>Lab 1</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Teacher</label>
                                <select class="custom-select">
                                    <option selected>All Teachers</option>
                                    <option>Ms. Patel</option>
                                    <option>Mr. Lewis</option>
                                    <option>Dr. Kim</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>View</label>
                                <div class="btn-group d-flex">
                                    <button type="button" class="btn btn-outline-primary active">Week</button>
                                    <button type="button" class="btn btn-outline-primary">Day</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive timetable-grid">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th class="time-col">Time</th>
                                    @if(!empty($days))
                                        @foreach($days as $day)
                                            <th>{{ $day }}</th>
                                        @endforeach
                                    @else
                                        <th>Mon</th>
                                        <th>Tue</th>
                                        <th>Wed</th>
                                        <th>Thu</th>
                                        <th>Fri</th>
                                        <th>Sat</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody id="timetable-body">
                                @if(!empty($timetable) && !empty($days) && !empty($time_slots))
                                    @php
                                        $firstDivisionKey = array_key_first($timetable);
                                    @endphp
                                    @foreach($time_slots as $slot)
                                        <tr>
                                            <td class="time-col">{{ str_replace('-', ' - ', $slot) }}</td>
                                            @foreach($days as $day)
                                                @php
                                                    $cell = $timetable[$firstDivisionKey][$day][$slot] ?? null;
                                                @endphp
                                                <td>
                                                    @if($cell)
                                                        <div class="slot bg-soft-blue">
                                                            <div class="slot-title">{{ $cell['subject'] }}</div>
                                                            <div class="slot-meta">{{ $cell['division_name'] }}</div>
                                                            <span class="badge badge-primary">{{ $cell['teacher'] }}</span>
                                                        </div>
                                                    @else
                                                        <div class="slot bg-soft-gray empty-slot">
                                                            <div class="slot-title">Free</div>
                                                            <div class="slot-meta">No class assigned</div>
                                                        </div>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Loading timetable...
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex flex-wrap align-items-center justify-content-between">
                    <div class="text-muted small">Last published: Feb 6, 2026 • 2 days ago</div>
                    <button class="btn btn-success">
                        <i class="fas fa-upload mr-1"></i> Publish Timetable
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-sliders-h mr-2"></i>Quick Stats</h3>
                </div>
                <div class="card-body">
                    <div class="info-box bg-light mb-3">
                        <span class="info-box-icon bg-primary"><i class="fas fa-school"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Divisions</span>
                            <span class="info-box-number">6</span>
                        </div>
                    </div>
                    <div class="info-box bg-light mb-3">
                        <span class="info-box-icon bg-success"><i class="fas fa-chalkboard-teacher"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Teachers</span>
                            <span class="info-box-number">24</span>
                        </div>
                    </div>
                    <div class="info-box bg-light">
                        <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Conflicts</span>
                            <span class="info-box-number">2</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list mr-2"></i>Unscheduled</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Art - Grade 10 A
                            <span class="badge badge-warning">2 slots</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Music - Grade 11 A
                            <span class="badge badge-warning">1 slot</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            PE - Grade 10 B
                            <span class="badge badge-warning">3 slots</span>
                        </li>
                    </ul>
                </div>
                <div class="card-footer">
                    <button class="btn btn-outline-primary btn-block">Resolve Conflicts</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-tags mr-2"></i>Legend</h3>
                </div>
                <div class="card-body">
                    <div class="legend-item"><span class="legend-swatch bg-soft-blue"></span>Math</div>
                    <div class="legend-item"><span class="legend-swatch bg-soft-green"></span>Science</div>
                    <div class="legend-item"><span class="legend-swatch bg-soft-teal"></span>Language</div>
                    <div class="legend-item"><span class="legend-swatch bg-soft-orange"></span>Social Studies</div>
                    <div class="legend-item"><span class="legend-swatch bg-soft-purple"></span>Electives</div>
                    <div class="legend-item"><span class="legend-swatch bg-soft-gray"></span>Free / Assembly</div>
                </div>
            </div>
        </div>
    </div>
@stop

@push('css')
<style>
    .timetable-grid table {
        min-width: 920px;
    }
    .timetable-grid .time-col {
        width: 130px;
        font-weight: 600;
        background: #f8f9fa;
        white-space: nowrap;
    }
    .slot {
        border-radius: 10px;
        padding: 10px 12px;
        min-height: 78px;
        border: 1px solid rgba(0, 0, 0, 0.06);
    }
    .slot-title {
        font-weight: 700;
        letter-spacing: 0.2px;
    }
    .slot-meta {
        font-size: 12px;
        color: #56606a;
        margin: 4px 0 6px;
    }
    .empty-slot {
        text-align: center;
        color: #7a838c;
    }
    .break-slot {
        text-align: center;
        background: repeating-linear-gradient(
            135deg,
            rgba(0, 0, 0, 0.03),
            rgba(0, 0, 0, 0.03) 10px,
            rgba(0, 0, 0, 0.06) 10px,
            rgba(0, 0, 0, 0.06) 20px
        );
        border: 1px dashed rgba(0, 0, 0, 0.15);
        font-weight: 600;
    }
    .bg-soft-blue { background: #e7f0ff; }
    .bg-soft-green { background: #e6f6ef; }
    .bg-soft-orange { background: #fff1dd; }
    .bg-soft-purple { background: #efe9ff; }
    .bg-soft-teal { background: #e3f7f7; }
    .bg-soft-gray { background: #f1f3f5; }
    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        font-size: 14px;
    }
    .legend-swatch {
        width: 18px;
        height: 18px;
        border-radius: 4px;
        margin-right: 10px;
        border: 1px solid rgba(0, 0, 0, 0.08);
    }
    .badge-teal {
        color: #0c5460;
        background-color: #d1f2f4;
    }
</style>
@endpush

@push('js')
<script>
    window.__TIMETABLE_DATA__ = {
        days: @json($days ?? []),
        timeSlots: @json($time_slots ?? []),
        timetable: @json($timetable ?? []),
        divisions: @json($divisions ?? []),
        generatedAt: @json($generated_at ?? null),
    };
</script>
<script>
    (function () {
        const state = window.__TIMETABLE_DATA__ || {};
        const tbody = document.getElementById('timetable-body');
        const divisionSelect = document.getElementById('division-select');

        const renderCell = (cell) => {
            if (!cell) {
                return `
                    <div class="slot bg-soft-gray empty-slot">
                        <div class="slot-title">Free</div>
                        <div class="slot-meta">No class assigned</div>
                    </div>
                `;
            }

            return `
                <div class="slot bg-soft-blue">
                    <div class="slot-title">${cell.subject}</div>
                    <div class="slot-meta">${cell.division_name}</div>
                    <span class="badge badge-primary">${cell.teacher}</span>
                </div>
            `;
        };

        const renderTable = (data, divisionId) => {
            if (!tbody) return;

            const days = data.days || [];
            const timeSlots = data.timeSlots || [];
            const timetable = data.timetable || {};

            if (!days.length || !timeSlots.length || !Object.keys(timetable).length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No timetable data available.
                        </td>
                    </tr>
                `;
                return;
            }

            const divisionKeys = Object.keys(timetable);
            const activeDivision = divisionId || divisionKeys[0];
            const rows = timeSlots.map((slot) => {
                const rowCells = days.map((day) => {
                    const cell = timetable[activeDivision]?.[day]?.[slot] || null;
                    return `<td>${renderCell(cell)}</td>`;
                }).join('');

                return `
                    <tr>
                        <td class="time-col">${slot.replace('-', ' - ')}</td>
                        ${rowCells}
                    </tr>
                `;
            }).join('');

            tbody.innerHTML = rows;
        };

        const hydrateDivisionSelect = (data) => {
            if (!divisionSelect) return;
            if (divisionSelect.options.length > 1) return;

            const divisions = data.divisions || [];
            divisions.forEach((division) => {
                const option = document.createElement('option');
                const className = division.class?.class_name || 'Class';
                option.value = division.id;
                option.textContent = `${className} - ${division.division_name}`;
                divisionSelect.appendChild(option);
            });
        };

        const loadData = async () => {
            if (state.days?.length && state.timeSlots?.length && Object.keys(state.timetable || {}).length) {
                hydrateDivisionSelect(state);
                renderTable(state, divisionSelect?.value);
                return;
            }

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch("{{ url('/admin/generateTimeTable') }}", {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                    },
                });
                const data = await response.json();
                state.days = data.days || [];
                state.timeSlots = data.time_slots || [];
                state.timetable = data.timetable || {};
                state.generatedAt = data.generated_at || null;

                hydrateDivisionSelect(state);
                renderTable(state, divisionSelect?.value);
            } catch (error) {
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center text-danger py-4">
                                Failed to load timetable data.
                            </td>
                        </tr>
                    `;
                }
            }
        };

        if (divisionSelect) {
            divisionSelect.addEventListener('change', () => {
                renderTable(state, divisionSelect.value || null);
            });
        }

        loadData();
    })();
</script>
@endpush
@section('js')
<script>
    $(document).ready(function () {
        $.ajax({
            url: "/admin/generateTimeTable",
            type: "POST",
            data: {
                 _token: "{{ csrf_token() }}",
            },
            suuccess: function (data) {
                console.log(data)
            }
        });
    })
</script>
@stop
