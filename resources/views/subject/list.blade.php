@extends('adminlte::page')

@section('title', 'Subjects')

@section('content_header')
    <x-page-header title="Subjects" />
@stop

@section('content')
    @include('partials.alerts')

    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h2 class="card-title">Add Subject</h2></div>
                <form action="{{ route('admin.subjects.store') }}" method="post">
                    @csrf
                    <div class="card-body">
                        <label for="subject_name">Subject Name</label>
                        <input type="text" id="subject_name" name="subject_name" class="form-control" value="{{ old('subject_name') }}" placeholder="Mathematics">
                        <label for="periods_per_week" class="mt-3">Periods per Week <small class="text-muted">(optional)</small></label>
                        <input type="number" id="periods_per_week" name="periods_per_week" class="form-control" min="1" max="60" value="{{ old('periods_per_week') }}">
                        <small class="form-text text-muted">Leave empty to share the week evenly with other subjects.</small>
                    </div>
                    <div class="card-footer"><button type="submit" class="btn btn-primary">Add Subject</button></div>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Periods per Week</th>
                                    <th>Teachers</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($subjects as $subject)
                                    <tr>
                                        <td><x-subject-chip :subject="$subject" /></td>
                                        <td>{{ $subject->periods_per_week ?? 'Auto' }}</td>
                                        <td>{{ $subject->teachers->map->full_name->join(', ') ?: '—' }}</td>
                                        <td class="text-right text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editSubject{{ $subject->id }}">Edit</button>
                                            <form action="{{ route('admin.subjects.destroy', $subject) }}" method="post" class="d-inline" onsubmit="return confirm('Delete {{ $subject->subject_name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">No subjects yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($subjects as $subject)
        <div class="modal fade" id="editSubject{{ $subject->id }}" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <form class="modal-content" action="{{ route('admin.subjects.update', $subject) }}" method="post">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit {{ $subject->subject_name }}</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <label for="subject-name-{{ $subject->id }}">Name</label>
                        <input id="subject-name-{{ $subject->id }}" type="text" name="subject_name" class="form-control" value="{{ $subject->subject_name }}">
                        <label for="periods-per-week-{{ $subject->id }}" class="mt-3">Periods per Week <small class="text-muted">(optional)</small></label>
                        <input id="periods-per-week-{{ $subject->id }}" type="number" name="periods_per_week" class="form-control" min="1" max="60" value="{{ $subject->periods_per_week }}">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@stop
