@extends('adminlte::page')

@section('title', 'Dashboard')

@section('content_header')
    <x-page-header title="Dashboard" :subtitle="collect([$academicYear?->year, now()->format('l, M j')])->filter()->join(' · ')" />
@stop

@php($unmarked = $todayByDivision->filter(fn ($row) => $row['students'] > 0 && $row['marked'] === 0)->count())

@section('content')
    @unless ($academicYear)
        <div class="alert alert-warning">There is no active academic year. <a href="{{ route('admin.academic-years.index') }}">Set one up</a> to get started.</div>
    @endunless

    <div class="row">
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="stat-card">
                <p class="stat-label">Active students</p>
                <p class="stat-value">{{ $studentCount }}</p>
                <p class="stat-context"><a href="{{ route('admin.students.index') }}">View students</a></p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="stat-card">
                <p class="stat-label">Active teachers</p>
                <p class="stat-value">{{ $teacherCount }}</p>
                <p class="stat-context"><a href="{{ route('admin.teachers.index') }}">View teachers</a></p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="stat-card">
                <p class="stat-label">Attendance today</p>
                <p class="stat-value">{{ $today && $today['percent'] !== null ? $today['percent'].'%' : '—' }}</p>
                <p class="stat-context">
                    @if ($unmarked > 0)
                        <span class="text-warning font-weight-bold">{{ $unmarked }} of {{ $todayByDivision->count() }} {{ Str::plural('division', $todayByDivision->count()) }} not marked yet</span>
                    @elseif ($todayByDivision->isNotEmpty())
                        <span class="text-success font-weight-bold">Every division marked</span>
                    @else
                        <span class="text-muted">No divisions yet</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="stat-card">
                <p class="stat-label">{{ $latestExam ? 'Pass rate · '.$latestExam->name : 'Pass rate' }}</p>
                <p class="stat-value">{{ $passRate !== null ? $passRate.'%' : '—' }}</p>
                <p class="stat-context">
                    @if ($latestExam)
                        <a href="{{ route('admin.reports.exams', ['exam' => $latestExam->id]) }}">Exam report</a>
                    @else
                        <span class="text-muted">No published results yet</span>
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-baseline">
                    <h2 class="card-title">Attendance Today</h2>
                    <span class="text-muted">{{ $todayByDivision->filter(fn ($row) => $row['marked'] > 0)->count() }} of {{ $todayByDivision->count() }} divisions marked</span>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($todayByDivision as $row)
                        <li class="list-group-item d-flex flex-wrap align-items-center" style="gap: .25rem 1rem">
                            <a href="{{ route('admin.reports.attendance', ['division' => $row['division']->id, 'from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="font-weight-bold" style="min-width: 7rem">{{ $row['division']->label }}</a>
                            <span class="text-muted flex-grow-1">{{ $row['classTeacher'] ?? 'No class teacher' }}</span>
                            @if ($row['students'] === 0)
                                <span class="badge badge-secondary">No students</span>
                            @elseif ($row['marked'] === 0)
                                <span class="badge badge-warning">Not marked</span>
                            @else
                                <span class="badge badge-success">Marked · {{ $row['attended'] }} of {{ $row['marked'] }} present</span>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No divisions yet. <a href="{{ route('admin.classes.index') }}">Add classes and divisions</a>.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title float-none">Low Attendance This Month</h2>
                    <p class="text-muted mb-0 small">Below {{ config('school.low_attendance_percent') }}%</p>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($lowAttendance as $row)
                        <li class="list-group-item d-flex flex-wrap align-items-center" style="gap: .25rem 1rem">
                            <a href="{{ route('admin.students.show', $row['student']) }}" class="font-weight-bold flex-grow-1">{{ $row['student']->first_name }} {{ $row['student']->last_name }}</a>
                            <span class="text-muted">{{ $row['division']?->label }}</span>
                            <span class="badge badge-danger">{{ $row['percent'] }}%</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No students below {{ config('school.low_attendance_percent') }}% this month.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Setup Checklist</h2></div>
                <ul class="list-group list-group-flush">
                    @foreach ([
                        ['Active academic year', (bool) $academicYear, route('admin.academic-years.index')],
                        ['Timetable published', $timetablePublished, route('admin.timetable')],
                        ['Exam results published', (bool) $latestExam, route('admin.exams.index')],
                    ] as [$label, $done, $url])
                        <li class="list-group-item d-flex align-items-center" style="gap: .6rem">
                            @if ($done)
                                <i class="fas fa-check text-success" aria-hidden="true"></i>
                            @else
                                <i class="far fa-circle text-muted" aria-hidden="true"></i>
                            @endif
                            <a href="{{ $url }}">{{ $label }}</a>
                            <span class="ml-auto text-muted small">{{ $done ? 'Done' : 'To do' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@stop
