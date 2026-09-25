@extends('adminlte::page')
@section('title', 'Parent Dashboard')

@section('content_header')
    <x-page-header :title="'Welcome, '.$parent->first_name" />
@stop

@section('content')
    <div class="row">
        @forelse ($children as $child)
            @php($student = $child['student'])
            <div class="col-md-6 col-xl-4">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h2 class="card-title">{{ $student->first_name }} {{ $student->last_name }}</h2>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><i class="fas fa-school mr-2 text-muted"></i>{{ $child['enrolment']?->division?->label ?? 'Not enrolled this year' }}</p>
                        <p class="mb-2"><i class="fas fa-clipboard-check mr-2 text-muted"></i>Attendance: <strong>{{ $child['attendance']['percent'] !== null ? $child['attendance']['percent'].'%' : '—' }}</strong>
                            <small class="text-muted">({{ $child['attendance']['absent'] }} absent, {{ $child['attendance']['late'] }} late)</small></p>
                        <p class="mb-0"><i class="fas fa-poll mr-2 text-muted"></i>
                            @if ($child['latestResult'] && $child['latestResult']['row'])
                                {{ $child['latestResult']['exam']->name }}: <strong>{{ $child['latestResult']['row']['grade'] }}</strong> · {{ $child['latestResult']['row']['result'] }}
                            @else
                                No published results yet
                            @endif
                        </p>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('parent.children.dashboard', $student) }}" class="btn btn-sm btn-primary">Overview</a>
                        <a href="{{ route('parent.children.timetable', $student) }}" class="btn btn-sm btn-outline-primary">Timetable</a>
                        <a href="{{ route('parent.children.attendance', $student) }}" class="btn btn-sm btn-outline-primary">Attendance</a>
                        <a href="{{ route('parent.children.results', $student) }}" class="btn btn-sm btn-outline-primary">Results</a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="alert alert-info">No children are linked to your account. Please contact the office.</div></div>
        @endforelse
    </div>
@stop
