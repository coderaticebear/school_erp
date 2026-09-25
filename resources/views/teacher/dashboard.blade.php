@extends('adminlte::page')
@section('title', 'Teacher Dashboard')

@section('content_header')
    <x-page-header :title="'Welcome, '.$teacher->full_name" />
@stop

@section('content')
    @include('partials.alerts')

    <div class="row">
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="stat-card">
                <p class="stat-label">Lessons today</p>
                <p class="stat-value">{{ $todaysLessons->count() }}</p>
                <p class="stat-context"><a href="{{ route('teacher.timetable') }}">My Timetable</a></p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="stat-card">
                <p class="stat-label">Attendance marked today</p>
                <p class="stat-value">{{ $markedToday->count() }} of {{ $teacher->divisions->count() }}</p>
                <p class="stat-context">
                    @if ($teacher->divisions->count() > $markedToday->count())
                        <a href="{{ route('teacher.attendance') }}">Take Attendance</a>
                    @else
                        <span class="text-success font-weight-bold">All marked</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="stat-card">
                <p class="stat-label">Divisions</p>
                <p class="stat-value">{{ $teacher->divisions->count() }}</p>
                <p class="stat-context"><a href="{{ route('teacher.classes') }}">My Classes</a></p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="stat-card">
                <p class="stat-label">Subjects</p>
                <p class="stat-value">{{ $teacher->subjects->count() }}</p>
                <p class="stat-context d-flex flex-wrap" style="gap: .35rem">
                    @foreach ($teacher->subjects as $subject)
                        <x-subject-chip :subject="$subject" />
                    @endforeach
                </p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Today, {{ now()->format('l, M j') }}</h2></div>
                <div class="card-body p-0">
                    @if (! $published)
                        <p class="text-muted p-3 mb-0">The timetable has not been published yet.</p>
                    @elseif ($todaysLessons->isEmpty())
                        <p class="text-muted p-3 mb-0">No lessons today.</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($todaysLessons as $lesson)
                                <li class="list-group-item d-flex flex-wrap align-items-center" style="gap: .35rem 1rem">
                                    <span class="text-muted text-nowrap" style="min-width: 6.5rem">{{ $lesson->period->time_range }}</span>
                                    <x-subject-chip :subject="$lesson->subject" />
                                    <span class="text-muted ml-auto">{{ $lesson->division->label }}</span>
                                </li>
                            @endforeach
                        </ul>
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
                                <a href="{{ route('teacher.attendance', ['division' => $division->id]) }}" class="btn btn-sm btn-outline-primary">Mark Attendance</a>
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
