@extends('adminlte::page')
@section('title', $student->first_name.' '.$student->last_name)

@section('content_header')
    @php($classTeacher = $enrolment?->division?->teachers->first())
    <x-page-header
        :title="$routePrefix === 'student.' ? 'Welcome, '.$student->first_name : $student->first_name.' '.$student->last_name"
        :subtitle="($enrolment?->division?->label ?? 'Not enrolled in a class this year').($classTeacher ? ' · Class teacher: '.$classTeacher->full_name : '')" />
@stop

@section('content')
    @include('portal.nav')

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="stat-card">
                <p class="stat-label">Attendance this year</p>
                <p class="stat-value">{{ $attendance['percent'] !== null ? $attendance['percent'].'%' : '—' }}</p>
                <p class="stat-context"><a href="{{ route($routePrefix.'attendance', $routeParams) }}">Attendance history</a></p>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card">
                <p class="stat-label">{{ $latestResult ? $latestResult['exam']->name : 'Latest result' }}</p>
                <p class="stat-value">{{ $latestResult && $latestResult['row']['percent'] !== null ? number_format($latestResult['row']['percent'], 1).'%' : '—' }}</p>
                <p class="stat-context">
                    @if ($latestResult && $latestResult['row'])
                        <span @class(['font-weight-bold', 'text-success' => $latestResult['row']['result'] === 'Pass', 'text-danger' => $latestResult['row']['result'] === 'Fail', 'text-muted' => $latestResult['row']['result'] === 'Incomplete'])>{{ $latestResult['row']['result'] }}</span>
                        · <a href="{{ route($routePrefix.'results', $routeParams) }}">Results</a>
                    @else
                        <span class="text-muted">No published results yet</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card">
                <p class="stat-label">Days absent</p>
                <p class="stat-value">{{ $attendance['absent'] }}</p>
                <p class="stat-context text-muted">{{ $attendance['late'] }} late · {{ $attendance['excused'] }} excused</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Today, {{ now()->format('l, M j') }}</h2></div>
                <div class="card-body p-0">
                    @if ($todaysLessons->isEmpty())
                        <p class="text-muted p-3 mb-0">No lessons to show today.</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($todaysLessons as $lesson)
                                <li class="list-group-item d-flex flex-wrap align-items-center" style="gap: .35rem 1rem">
                                    <span class="text-muted text-nowrap" style="min-width: 6.5rem">{{ $lesson->period->time_range }}</span>
                                    <x-subject-chip :subject="$lesson->subject" />
                                    <span class="text-muted ml-auto">{{ $lesson->teacher->full_name }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Recent Absences and Late Arrivals</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($recentAbsences as $record)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $record->date->format('D, M j') }}{{ $record->remark ? ' · '.$record->remark : '' }}</span>
                            <span class="badge {{ \App\Models\Attendance::badgeClass($record->status) }}">{{ \App\Models\Attendance::STATUSES[$record->status] }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">None this year.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@stop
