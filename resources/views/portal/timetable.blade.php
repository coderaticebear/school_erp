@extends('adminlte::page')
@section('title', 'Timetable')

@section('content_header')
    <x-page-header title="Timetable" :subtitle="$student->first_name.' '.$student->last_name">
        @if ($grid)
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print mr-1" aria-hidden="true"></i> Print</button>
        @endif
    </x-page-header>
@stop

@section('content')
    @include('portal.nav')

    @if (! $academicYear)
        <div class="alert alert-info">There is no active academic year.</div>
    @elseif (! $enrolment)
        <div class="alert alert-info">{{ $student->first_name }} is not enrolled in a class this year.</div>
    @elseif (! $grid)
        <div class="alert alert-info">The timetable for {{ $academicYear->year }} has not been published yet.</div>
    @else
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ $enrolment->division->label }} · {{ $academicYear->year }}</h2></div>
            <div class="card-body">
                @include('timetable.grid', ['grid' => $grid, 'mode' => 'division'])
            </div>
        </div>
    @endif
@stop

@include('timetable.styles')
