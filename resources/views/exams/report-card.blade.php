@extends('adminlte::page')
@section('title', 'Report Card')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center no-print">
        <h1>Report Card</h1>
        <div>
            @isset($backUrl)
                <a href="{{ $backUrl }}" class="btn btn-secondary">Back</a>
            @endisset
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
        </div>
    </div>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between mb-3">
                <div>
                    <h3 class="mb-0">{{ $student->first_name }} {{ $student->last_name }}</h3>
                    <div class="text-muted">{{ $division?->label ?? 'No class' }} · {{ $exam->academicYear->year ?? '' }}</div>
                </div>
                <div class="text-right">
                    <h4 class="mb-0">{{ $exam->name }}</h4>
                    <div class="text-muted">Out of {{ $exam->max_marks }} per subject · pass mark {{ $exam->pass_marks }}</div>
                </div>
            </div>

            @if (! $row)
                <div class="alert alert-info mb-0">This student was not enrolled in a division for this exam's academic year.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Subject</th>
                                <th class="text-center">Marks</th>
                                <th class="text-center">Grade</th>
                                <th>Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subjects as $subject)
                                @php($mark = $row['marks'][$subject->id] ?? null)
                                <tr>
                                    <td>{{ $subject->subject_name }}</td>
                                    <td @class(['text-center', 'text-danger' => $mark && ($mark->is_absent || $mark->marks < $exam->pass_marks)])>
                                        {{ $mark ? ($mark->is_absent ? 'Absent' : rtrim(rtrim(number_format($mark->marks, 2), '0'), '.').' / '.$exam->max_marks) : 'Not entered' }}
                                    </td>
                                    <td class="text-center">{{ $mark ? ($mark->is_absent ? 'F' : $exam->gradeFor($mark->marks)) : '—' }}</td>
                                    <td>{{ $mark?->remark }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-weight-bold">
                                <td>Total</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($row['total'], 2), '0'), '.') }} / {{ $row['max_total'] }}</td>
                                <td class="text-center">{{ $row['grade'] }}</td>
                                <td>{{ $row['percent'] !== null ? number_format($row['percent'], 1).'%' : '' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="d-flex flex-wrap">
                    <div class="mr-4"><strong>Result:</strong>
                        <span @class(['badge', 'badge-success' => $row['result'] === 'Pass', 'badge-danger' => $row['result'] === 'Fail', 'badge-secondary' => $row['result'] === 'Incomplete'])>{{ $row['result'] }}</span>
                    </div>
                    <div><strong>Rank:</strong> {{ $row['rank'] ? $row['rank'].' of '.$class_size : '—' }}</div>
                </div>
            @endif
        </div>
    </div>
@stop

@include('timetable.styles')
