@extends('adminlte::page')
@section('title', 'Exams')

@section('content_header')
    <h1>Exams {{ $academicYear ? '· '.$academicYear->year : '' }}</h1>
@stop

@section('content')
    @include('partials.alerts')

    @if (! $academicYear)
        <div class="alert alert-warning">There is no active academic year. <a href="{{ route('admin.academic-years.index') }}">Activate one</a> first.</div>
    @else
        <div class="row">
            <div class="col-md-4">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Add Exam</h3></div>
                    <form action="{{ route('admin.exams.store') }}" method="post">
                        @csrf
                        <div class="card-body">
                            @include('admin.exams.fields', ['exam' => null])
                        </div>
                        <div class="card-footer"><button type="submit" class="btn btn-primary">Add</button></div>
                    </form>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Exam</th>
                                    <th>Starts</th>
                                    <th>Marks</th>
                                    <th>Results</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($exams as $exam)
                                    <tr>
                                        <td><a href="{{ route('admin.exams.show', $exam) }}">{{ $exam->name }}</a></td>
                                        <td>{{ $exam->starts_on?->format('M j, Y') ?? '—' }}</td>
                                        <td>{{ $exam->pass_marks }} / {{ $exam->max_marks }} <small class="text-muted">({{ $exam->marks_count }} entered)</small></td>
                                        <td>
                                            @if ($exam->isPublished())
                                                <span class="badge badge-success">Published</span>
                                            @else
                                                <span class="badge badge-secondary">Draft</span>
                                            @endif
                                        </td>
                                        <td class="text-right text-nowrap">
                                            <a href="{{ route('admin.exams.show', $exam) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#editExam{{ $exam->id }}">Edit</button>
                                            @if ($exam->marks_count === 0)
                                                <form action="{{ route('admin.exams.destroy', $exam) }}" method="post" class="d-inline" onsubmit="return confirm('Delete {{ $exam->name }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">No exams yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @foreach ($exams as $exam)
            <div class="modal fade" id="editExam{{ $exam->id }}" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <form class="modal-content" action="{{ route('admin.exams.update', $exam) }}" method="post">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Edit {{ $exam->name }}</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">@include('admin.exams.fields', ['exam' => $exam])</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
@stop
