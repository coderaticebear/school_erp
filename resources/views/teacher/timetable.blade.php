@extends('adminlte::page')
@section('title', 'My Timetable')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>My Timetable</h1>
        @if ($grid)
            <button type="button" class="btn btn-outline-secondary no-print" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
        @endif
    </div>
@stop

@section('content')
    @if (! $academicYear)
        <div class="alert alert-info">There is no active academic year.</div>
    @elseif (! $grid)
        <div class="alert alert-info">The timetable for {{ $academicYear->year }} has not been published yet.</div>
    @else
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ $teacher->full_name }} · {{ $academicYear->year }}</h3>
            </div>
            <div class="card-body">
                @include('timetable.grid', ['grid' => $grid, 'mode' => 'teacher'])
            </div>
        </div>
    @endif
@stop

@include('timetable.styles')
