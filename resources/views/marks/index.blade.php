@extends('adminlte::page')
@section('title', 'Marks Entry')

@section('content_header')
    <h1>Marks Entry</h1>
@stop

@section('content')
    @include('partials.alerts')

    @if (! $academicYear)
        <div class="alert alert-info">There is no active academic year.</div>
    @elseif ($exams->isEmpty())
        <div class="alert alert-info">No exams have been set up for {{ $academicYear->year }} yet.</div>
    @else
        <form method="get" action="{{ route('marks.index') }}" class="form-inline mb-3">
            <label class="mr-2" for="exam">Exam</label>
            <select name="exam" id="exam" class="custom-select" onchange="this.form.submit()">
                @foreach ($exams as $option)
                    <option value="{{ $option->id }}" @selected($option->id === $exam->id)>{{ $option->name }}</option>
                @endforeach
            </select>
            @if ($exam->isPublished())
                <span class="badge badge-success ml-3">Results published — marks are locked</span>
            @endif
        </form>

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Division</th>
                            <th>Subject</th>
                            <th>Progress</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sheets as $sheet)
                            @php($done = $sheet['students'] > 0 && $sheet['entered'] >= $sheet['students'])
                            <tr>
                                <td>{{ $sheet['division']->label }}</td>
                                <td>{{ $sheet['subject']->subject_name }}</td>
                                <td>
                                    <span @class(['badge', 'badge-success' => $done, 'badge-warning' => ! $done && $sheet['entered'] > 0, 'badge-light' => $sheet['entered'] === 0])>
                                        {{ $sheet['entered'] }}/{{ $sheet['students'] }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('marks.sheet', [$exam, $sheet['division'], $sheet['subject']]) }}" class="btn btn-sm btn-outline-primary">
                                        {{ $exam->isPublished() ? 'View' : ($sheet['entered'] ? 'Edit marks' : 'Enter marks') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">You have no division and subject to enter marks for.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@stop
