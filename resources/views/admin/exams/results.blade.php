@extends('adminlte::page')
@section('title', $exam->name.' Results')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1>{{ $exam->name }} · {{ $division->label }}</h1>
        <div class="no-print">
            <a href="{{ route('admin.exams.results', [$exam, $division, 'format' => 'csv']) }}" class="btn btn-outline-secondary"><i class="fas fa-file-export mr-1"></i> Export</a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
        </div>
    </div>
@stop

@section('content')
    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Rank</th>
                        <th>Student</th>
                        @foreach ($subjects as $subject)
                            <th class="text-center">{{ $subject->subject_name }}</th>
                        @endforeach
                        <th class="text-center">Total</th>
                        <th class="text-center">%</th>
                        <th class="text-center">Grade</th>
                        <th>Result</th>
                        <th class="no-print"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows->sortBy(fn ($row) => [$row['rank'] ?? PHP_INT_MAX, $row['student']->first_name]) as $row)
                        <tr>
                            <td>{{ $row['rank'] ?? '—' }}</td>
                            <td>{{ $row['student']->first_name }} {{ $row['student']->last_name }}</td>
                            @foreach ($subjects as $subject)
                                @php($mark = $row['marks'][$subject->id] ?? null)
                                <td @class(['text-center', 'text-danger' => $mark && ($mark->is_absent || $mark->marks < $exam->pass_marks), 'text-muted' => ! $mark])>
                                    {{ $mark ? ($mark->is_absent ? 'AB' : rtrim(rtrim(number_format($mark->marks, 2), '0'), '.')) : '·' }}
                                </td>
                            @endforeach
                            <td class="text-center">{{ rtrim(rtrim(number_format($row['total'], 2), '0'), '.') }} / {{ $row['max_total'] }}</td>
                            <td class="text-center">{{ $row['percent'] !== null ? number_format($row['percent'], 1) : '—' }}</td>
                            <td class="text-center"><strong>{{ $row['grade'] }}</strong></td>
                            <td>
                                <span @class(['badge', 'badge-success' => $row['result'] === 'Pass', 'badge-danger' => $row['result'] === 'Fail', 'badge-secondary' => $row['result'] === 'Incomplete'])>{{ $row['result'] }}</span>
                            </td>
                            <td class="no-print"><a href="{{ route('admin.exams.report-card', [$exam, $row['student']]) }}" class="btn btn-xs btn-outline-primary">Report card</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $subjects->count() + 7 }}" class="text-center text-muted">No students are enrolled in {{ $division->label }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <a href="{{ route('admin.exams.show', $exam) }}" class="btn btn-secondary no-print">Back to {{ $exam->name }}</a>
@stop

@include('timetable.styles')
