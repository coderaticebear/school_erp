@extends('adminlte::page')
@section('title', 'Attendance Report')

@section('content_header')
    <x-page-header title="Attendance Report">
        <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="btn btn-outline-secondary"><i class="fas fa-file-export mr-1" aria-hidden="true"></i> Export Students</a>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print mr-1" aria-hidden="true"></i> Print</button>
    </x-page-header>
@stop

@section('content')
    @if (! $academicYear)
        <div class="alert alert-warning">There is no active academic year.</div>
    @else
        <form method="get" class="card card-body no-print">
            <div class="form-row align-items-end">
                <div class="col-md-3"><label for="from">From</label><input id="from" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}"></div>
                <div class="col-md-3"><label for="to">To</label><input id="to" type="date" name="to" class="form-control" value="{{ $to->toDateString() }}"></div>
                <div class="col-md-4">
                    <label for="division">Division</label>
                    <select id="division" name="division" class="custom-select">
                        <option value="">All divisions</option>
                        @foreach ($divisions as $option)
                            <option value="{{ $option->id }}" @selected($division?->id === $option->id)>{{ $option->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary btn-block">Show Report</button></div>
            </div>
        </form>

        <p class="text-muted">{{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}. Present and late count as attended.</p>

        @unless ($division)
            <div class="card">
                <div class="card-header"><h2 class="card-title">By Division</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th class="pl-3">Division</th><th>Students</th><th>Days Marked</th><th>Attendance</th></tr></thead>
                            <tbody>
                                @foreach ($byDivision as $row)
                                    <tr>
                                        <td class="pl-3"><a href="{{ request()->fullUrlWithQuery(['division' => $row['division']->id]) }}">{{ $row['division']->label }}</a></td>
                                        <td>{{ $row['students'] }}</td>
                                        <td>{{ $row['days'] }}</td>
                                        <td style="min-width: 200px">
                                            @if ($row['percent'] !== null)
                                                <div class="progress progress-sm mb-1"><div class="progress-bar {{ $row['percent'] < config('school.low_attendance_percent') ? 'bg-danger' : 'bg-success' }}" style="width: {{ $row['percent'] }}%"></div></div>
                                                <small>{{ $row['percent'] }}%</small>
                                            @else
                                                <span class="text-muted">Not marked</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endunless

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">{{ $division ? 'Students in '.$division->label : 'Students below '.config('school.low_attendance_percent').'%' }}</h2>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th class="pl-3">Student</th>@unless ($division)<th>Division</th>@endunless<th>Present</th><th>Late</th><th>Absent</th><th>Excused</th><th>%</th></tr></thead>
                        <tbody>
                            @forelse ($byStudent as $row)
                                <tr>
                                    <td class="pl-3"><a href="{{ route('admin.students.show', $row['student']) }}">{{ $row['student']->first_name }} {{ $row['student']->last_name }}</a></td>
                                    @unless ($division)<td>{{ $row['division']?->label }}</td>@endunless
                                    <td>{{ $row['present'] }}</td>
                                    <td>{{ $row['late'] }}</td>
                                    <td>{{ $row['absent'] }}</td>
                                    <td>{{ $row['excused'] }}</td>
                                    <td><span class="badge {{ $row['percent'] < config('school.low_attendance_percent') ? 'badge-danger' : 'badge-success' }}">{{ $row['percent'] }}%</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">{{ $division ? 'No attendance recorded in this period.' : 'No students below the threshold.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@stop

@include('timetable.styles')
