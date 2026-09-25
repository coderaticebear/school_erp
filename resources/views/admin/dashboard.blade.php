@extends('adminlte::page')

@section('title', 'Admin Dashboard')

@section('content_header')
    <h1>Dashboard <small class="text-muted">{{ $academicYear?->year }}</small></h1>
@stop

@section('content')
    @unless ($academicYear)
        <div class="alert alert-warning">There is no active academic year. <a href="{{ route('admin.academic-years.index') }}">Set one up</a> to get started.</div>
    @endunless

    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner"><p class="small-box-value">{{ $teacherCount }}</p><p>Active teachers</p></div>
                <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <a href="{{ route('admin.teachers.index') }}" class="small-box-footer">Manage <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner"><p class="small-box-value">{{ $studentCount }}</p><p>Active students</p></div>
                <div class="icon"><i class="fas fa-user-graduate"></i></div>
                <a href="{{ route('admin.students.index') }}" class="small-box-footer">Manage <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <p class="small-box-value">{{ $today && $today['percent'] !== null ? $today['percent'].'%' : '—' }}</p>
                    <p>Attendance today · {{ $today['marked_divisions'] ?? 0 }}/{{ $today['divisions'] ?? 0 }} divisions marked</p>
                </div>
                <div class="icon"><i class="fas fa-chart-pie"></i></div>
                <a href="{{ route('admin.reports.attendance') }}" class="small-box-footer">Attendance Report <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <p class="small-box-value">{{ $passRate !== null ? $passRate.'%' : '—' }}</p>
                    <p>{{ $latestExam ? 'Pass rate · '.$latestExam->name : 'No published results yet' }}</p>
                </div>
                <div class="icon"><i class="fas fa-poll"></i></div>
                <a href="{{ route('admin.reports.exams', $latestExam ? ['exam' => $latestExam->id] : []) }}" class="small-box-footer">Exam Report <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title"><i class="fas fa-user-clock mr-2"></i>Low Attendance This Month (Below {{ config('school.low_attendance_percent') }}%)</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($lowAttendance as $row)
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="{{ route('admin.students.show', $row['student']) }}">{{ $row['student']->first_name }} {{ $row['student']->last_name }}</a>
                            <span><small class="text-muted mr-2">{{ $row['division']?->label }}</small><span class="badge badge-danger">{{ $row['percent'] }}%</span></span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No students below the threshold this month.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title"><i class="fas fa-tasks mr-2"></i>Setup Checklist</h2></div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><i class="fas {{ $academicYear ? 'fa-check-circle text-success' : 'fa-circle text-muted' }} mr-2"></i><a href="{{ route('admin.academic-years.index') }}">Active academic year</a></li>
                    <li class="list-group-item"><i class="fas {{ $timetablePublished ? 'fa-check-circle text-success' : 'fa-circle text-muted' }} mr-2"></i><a href="{{ route('admin.timetable') }}">Timetable published</a></li>
                    <li class="list-group-item"><i class="fas {{ $latestExam ? 'fa-check-circle text-success' : 'fa-circle text-muted' }} mr-2"></i><a href="{{ route('admin.exams.index') }}">Exam results published</a></li>
                </ul>
            </div>
        </div>
    </div>
@stop
