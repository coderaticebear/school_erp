@extends('adminlte::page')
@section('title', 'My Classes')

@section('content_header')
    <x-page-header title="My Classes" />
@stop

@section('content')
    <div class="row">
        @forelse ($divisions as $division)
            <div class="col-md-6 col-lg-4">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h2 class="card-title">{{ $division->label }}</h2>
                        @if ($division->pivot->class_teacher)
                            <div class="card-tools"><span class="badge badge-primary">Class Teacher</span></div>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><i class="fas fa-user-graduate mr-2 text-muted"></i>{{ $division->students_count }} {{ Str::plural('student', $division->students_count) }}</p>
                        <p class="mb-0"><i class="fas fa-book mr-2 text-muted"></i>{{ ($subjectsByDivision[$division->id] ?? collect())->join(', ') ?: 'No lessons scheduled' }}</p>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('teacher.classes.show', $division) }}" class="btn btn-sm btn-outline-primary">Students</a>
                        <a href="{{ route('teacher.attendance', ['division' => $division->id]) }}" class="btn btn-sm btn-outline-success">Attendance</a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="alert alert-info">You are not assigned to any division yet. Please contact the office.</div></div>
        @endforelse
    </div>
@stop
