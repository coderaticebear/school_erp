@extends('adminlte::page')
@section('title', 'Exam Report')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1>Exam Report</h1>
        @if ($exam)
            <div class="no-print">
                <a href="{{ route('admin.reports.exams', ['exam' => $exam->id, 'format' => 'csv']) }}" class="btn btn-outline-secondary"><i class="fas fa-file-export mr-1"></i> Export</a>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
            </div>
        @endif
    </div>
@stop

@section('content')
    @if (! $exam)
        <div class="alert alert-info">There are no exams for the active academic year yet.</div>
    @else
        <form method="get" class="form-inline mb-3 no-print">
            <label class="mr-2" for="exam">Exam</label>
            <select name="exam" id="exam" class="custom-select" onchange="this.form.submit()">
                @foreach ($exams as $option)
                    <option value="{{ $option->id }}" @selected($option->id === $exam->id)>{{ $option->name }}{{ $option->isPublished() ? '' : ' (draft)' }}</option>
                @endforeach
            </select>
        </form>

        <div class="card">
            <div class="card-header"><h3 class="card-title">By division</h3></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th class="pl-3">Division</th><th>Students</th><th>Complete</th><th>Passed</th><th>Pass rate</th><th>Average</th><th>Top student</th></tr></thead>
                    <tbody>
                        @foreach ($byDivision as $row)
                            <tr>
                                <td class="pl-3"><a href="{{ route('admin.exams.results', [$exam, $row['division']]) }}">{{ $row['division']->label }}</a></td>
                                <td>{{ $row['students'] }}</td>
                                <td>{{ $row['complete'] }}</td>
                                <td>{{ $row['passed'] }}</td>
                                <td>{{ $row['pass_rate'] !== null ? $row['pass_rate'].'%' : '—' }}</td>
                                <td>{{ $row['average'] !== null ? $row['average'].'%' : '—' }}</td>
                                <td>{{ $row['top'] ? $row['top']['student']->first_name.' '.$row['top']['student']->last_name.' ('.number_format($row['top']['percent'], 1).'%)' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">By subject <small class="text-muted">(out of {{ $exam->max_marks }}, pass {{ $exam->pass_marks }})</small></h3></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th class="pl-3">Subject</th><th>Entries</th><th>Absent</th><th>Average</th><th>Highest</th><th>Pass rate</th></tr></thead>
                    <tbody>
                        @forelse ($bySubject as $row)
                            <tr>
                                <td class="pl-3">{{ $row['subject'] }}</td>
                                <td>{{ $row['entries'] }}</td>
                                <td>{{ $row['absent'] }}</td>
                                <td>{{ $row['average'] ?? '—' }}</td>
                                <td>{{ $row['highest'] ?? '—' }}</td>
                                <td>{{ $row['pass_rate'] !== null ? $row['pass_rate'].'%' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">No marks entered yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@stop

@include('timetable.styles')
