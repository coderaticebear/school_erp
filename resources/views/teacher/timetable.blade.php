@extends('adminlte::page')
@section('title', 'My Timetable')

@section('content_header')
    <x-page-header title="My Timetable">
        @if ($grid)
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print mr-1" aria-hidden="true"></i> Print</button>
        @endif
    </x-page-header>
@stop

@section('content')
    @if (! $academicYear)
        <div class="alert alert-info">There is no active academic year.</div>
    @elseif (! $grid)
        <div class="alert alert-info">The timetable for {{ $academicYear->year }} has not been published yet.</div>
    @else
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">{{ $teacher->full_name }} · {{ $academicYear->year }}</h2>
            </div>
            <div class="card-body">
                @include('timetable.grid', ['grid' => $grid, 'mode' => 'teacher'])
            </div>
        </div>
    @endif
@stop

@include('timetable.styles')
