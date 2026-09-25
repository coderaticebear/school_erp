@extends('adminlte::page')
@section('title', 'Marks · '.$subject->subject_name)

@section('content_header')
    <h1>{{ $exam->name }} · {{ $subject->subject_name }} · {{ $division->label }}</h1>
@stop

@section('content')
    @include('partials.alerts')

    <p class="text-muted">Out of {{ $exam->max_marks }} · pass mark {{ $exam->pass_marks }}. Leave a row empty if the mark is not ready yet.</p>

    @if ($students->isEmpty())
        <div class="alert alert-info">No students are enrolled in {{ $division->label }}.</div>
    @else
        <form method="post" action="{{ route('marks.save', [$exam, $division, $subject]) }}">
            @csrf
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th style="width: 160px">Marks</th>
                                    <th style="width: 100px">Absent</th>
                                    <th>Remark</th>
                                    <th style="width: 80px">Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $student)
                                    @php($mark = $existing[$student->id] ?? null)
                                    @php($value = old("marks.{$student->id}.value", $mark && ! $mark->is_absent ? rtrim(rtrim(number_format($mark->marks, 2, '.', ''), '0'), '.') : ''))
                                    @php($absent = (bool) old("marks.{$student->id}.absent", $mark?->is_absent))
                                    <tr>
                                        <td class="align-middle">{{ $student->first_name }} {{ $student->last_name }}</td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="{{ $exam->max_marks }}" name="marks[{{ $student->id }}][value]" aria-label="Marks for {{ $student->first_name }} {{ $student->last_name }}" value="{{ $value }}"
                                                   @class(['form-control', 'form-control-sm', 'is-invalid' => $errors->has("marks.{$student->id}.value")]) @disabled($exam->isPublished())>
                                            @error("marks.{$student->id}.value")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </td>
                                        <td class="align-middle">
                                            <input type="checkbox" name="marks[{{ $student->id }}][absent]" aria-label="{{ $student->first_name }} {{ $student->last_name }} was absent" value="1" @checked($absent) @disabled($exam->isPublished())>
                                        </td>
                                        <td>
                                            <input type="text" name="marks[{{ $student->id }}][remark]" aria-label="Remark for {{ $student->first_name }} {{ $student->last_name }}" maxlength="255" class="form-control form-control-sm" value="{{ old("marks.{$student->id}.remark", $mark?->remark) }}" @disabled($exam->isPublished())>
                                        </td>
                                        <td class="align-middle">
                                            @if ($mark)
                                                <span class="badge {{ $mark->is_absent || $mark->marks < $exam->pass_marks ? 'badge-danger' : 'badge-success' }}">{{ $mark->is_absent ? 'AB' : $exam->gradeFor($mark->marks) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('marks.index', ['exam' => $exam->id]) }}" class="btn btn-secondary">Back</a>
                    @unless ($exam->isPublished())
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Marks</button>
                    @endunless
                </div>
            </div>
        </form>
    @endif
@stop
