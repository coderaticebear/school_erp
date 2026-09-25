@extends('adminlte::page')
@section('title', 'Parent Dashboard')

@section('content_header')
    <x-page-header :title="'Welcome, '.$parent->first_name" />
@stop

@section('content')
    <div class="row">
        @forelse ($children as $child)
            @php($student = $child['student'])
            <div class="col-lg-6 mb-3">
                <div class="card h-100 mb-0">
                    <div class="card-header">
                        <h2 class="card-title float-none">{{ $student->first_name }} {{ $student->last_name }}</h2>
                        <p class="text-muted mb-0 small">{{ $child['enrolment']?->division?->label ?? 'Not enrolled this year' }}</p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <p class="stat-label">Attendance this year</p>
                                <p class="stat-value">{{ $child['attendance']['percent'] !== null ? $child['attendance']['percent'].'%' : '—' }}</p>
                                <p class="stat-context text-muted">{{ $child['attendance']['absent'] }} absent · {{ $child['attendance']['late'] }} late</p>
                            </div>
                            <div class="col-6">
                                @if ($child['latestResult'] && $child['latestResult']['row'])
                                    @php($row = $child['latestResult']['row'])
                                    <p class="stat-label">{{ $child['latestResult']['exam']->name }}</p>
                                    <p class="stat-value">{{ $row['grade'] }}</p>
                                    <p @class(['stat-context', 'font-weight-bold', 'text-success' => $row['result'] === 'Pass', 'text-danger' => $row['result'] === 'Fail', 'text-muted' => $row['result'] === 'Incomplete'])>{{ $row['result'] }}{{ $row['percent'] !== null ? ' · '.number_format($row['percent'], 1).'%' : '' }}</p>
                                @else
                                    <p class="stat-label">Latest result</p>
                                    <p class="stat-value">—</p>
                                    <p class="stat-context text-muted">No published results yet</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex flex-wrap" style="gap: .5rem">
                        <a href="{{ route('parent.children.dashboard', $student) }}" class="btn btn-sm btn-primary">Overview<span class="sr-only"> for {{ $student->first_name }}</span></a>
                        <a href="{{ route('parent.children.timetable', $student) }}" class="btn btn-sm btn-outline-secondary">Timetable<span class="sr-only"> for {{ $student->first_name }}</span></a>
                        <a href="{{ route('parent.children.attendance', $student) }}" class="btn btn-sm btn-outline-secondary">Attendance<span class="sr-only"> for {{ $student->first_name }}</span></a>
                        <a href="{{ route('parent.children.results', $student) }}" class="btn btn-sm btn-outline-secondary">Results<span class="sr-only"> for {{ $student->first_name }}</span></a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="alert alert-info">No children are linked to your account. Please contact the office.</div></div>
        @endforelse
    </div>
@stop
