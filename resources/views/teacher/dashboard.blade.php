@extends('adminlte::page')
@section('title', 'Teacher Dashboard')

@section('content_header')
    <h1>Welcome, {{ $teacher->full_name }}</h1>
@stop

@section('content')
    @include('partials.alerts')

    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <p class="small-box-value">{{ $teacher->divisions->count() }}</p>
                    <p>Divisions</p>
                </div>
                <div class="icon"><i class="fas fa-school"></i></div>
                <a href="{{ route('teacher.classes') }}" class="small-box-footer">My Classes <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <p class="small-box-value">{{ $todaysLessons->count() }}</p>
                    <p>Lessons today</p>
                </div>
                <div class="icon"><i class="fas fa-chalkboard"></i></div>
                <a href="{{ route('teacher.timetable') }}" class="small-box-footer">My Timetable <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <p class="small-box-value">{{ $markedToday->count() }}/{{ $teacher->divisions->count() }}</p>
                    <p>Attendance marked today</p>
                </div>
                <div class="icon"><i class="fas fa-clipboard-check"></i></div>
                <a href="{{ route('teacher.attendance') }}" class="small-box-footer">Take Attendance <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <p class="small-box-value">{{ $teacher->subjects->count() }}</p>
                    <p>{{ $teacher->subjects->pluck('subject_name')->join(', ') ?: 'Subjects' }}</p>
                </div>
                <div class="icon"><i class="fas fa-book"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h2 class="card-title"><i class="fas fa-calendar-day mr-2"></i>Today, {{ now()->format('l, M j') }}</h2></div>
                <div class="card-body p-0">
                    @if (! $published)
                        <p class="text-muted p-3 mb-0">The timetable has not been published yet.</p>
                    @elseif ($todaysLessons->isEmpty())
                        <p class="text-muted p-3 mb-0">No lessons today.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <tbody>
                                    @foreach ($todaysLessons as $lesson)
                                        <tr>
                                            <td class="text-nowrap text-muted">{{ $lesson->period->time_range }}</td>
                                            <td><strong>{{ $lesson->subject->subject_name }}</strong></td>
                                            <td>{{ $lesson->division->label }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h2 class="card-title"><i class="fas fa-users mr-2"></i>My Divisions</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($teacher->divisions->sortBy('label') as $division)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <a href="{{ route('teacher.classes.show', $division) }}">{{ $division->label }}</a>
                                @if ($division->pivot->class_teacher)
                                    <span class="badge badge-primary ml-1">Class Teacher</span>
                                @endif
                            </span>
                            @if ($markedToday->contains($division->id))
                                <span class="badge badge-success">Marked</span>
                            @else
                                <a href="{{ route('teacher.attendance', ['division' => $division->id]) }}" class="badge badge-warning">Mark Attendance</a>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted">You are not assigned to any division yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@stop
