@extends('adminlte::page')
@section('title', 'Results')

@section('content_header')
    <h1>Results · {{ $student->first_name }}</h1>
@stop

@section('content')
    @include('portal.nav')

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th class="pl-3">Exam</th><th>Percentage</th><th>Grade</th><th>Result</th><th>Rank</th><th><span class="sr-only">Report Card</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($results as $result)
                            @php($row = $result['row'])
                            <tr>
                                <td class="pl-3">{{ $result['exam']->name }}</td>
                                <td>{{ $row && $row['percent'] !== null ? number_format($row['percent'], 1).'%' : '—' }}</td>
                                <td><strong>{{ $row['grade'] ?? '—' }}</strong></td>
                                <td>
                                    @if ($row)
                                        <span @class(['badge', 'badge-success' => $row['result'] === 'Pass', 'badge-danger' => $row['result'] === 'Fail', 'badge-secondary' => $row['result'] === 'Incomplete'])>{{ $row['result'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $row && $row['rank'] ? $row['rank'].' of '.$result['class_size'] : '—' }}</td>
                                <td class="text-right pr-3"><a href="{{ route($routePrefix.'report-card', [...$routeParams, $result['exam']]) }}" class="btn btn-sm btn-outline-primary">Report Card</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">No results have been published yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop
