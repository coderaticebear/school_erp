@extends('adminlte::page')

@section('title', 'Classes & Divisions')

@section('content_header')
    <x-page-header title="Classes & Divisions">
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addClass">
            <i class="fas fa-plus mr-1" aria-hidden="true"></i> Add Class
        </button>
    </x-page-header>
@stop

@section('content')
    @include('partials.alerts')

    @unless ($academicYear)
        <div class="alert alert-warning">No academic year is active, so student counts are empty. <a href="{{ route('admin.academic-years.index') }}">Activate one</a>.</div>
    @endunless

    <div class="row">
        @forelse ($classes as $class)
            <div class="col-lg-6">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h2 class="card-title">{{ $class->class_name }}</h2>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-toggle="modal" data-target="#editClass{{ $class->id }}" title="Rename class"><i class="fas fa-pen"></i></button>
                            <form action="{{ route('admin.classes.destroy', $class) }}" method="post" class="d-inline" data-confirm-title="Delete {{ $class->class_name }}?" data-confirm-body="Its divisions and their teacher assignments are deleted too. Classes with students, attendance or marks can't be deleted." data-confirm-button="Delete Class" data-confirm-tone="danger">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-tool text-danger" title="Delete class"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th class="pl-3">Division</th>
                                        <th>Students</th>
                                        <th>Class Teacher</th>
                                        <th class="text-right pr-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($class->divisions as $division)
                                        <tr>
                                            <td class="pl-3">{{ $division->division_name }}</td>
                                            <td>{{ $division->students_count }}</td>
                                            <td>{{ $division->teachers->first()?->full_name ?? '—' }}</td>
                                            <td class="text-right pr-3 text-nowrap">
                                                <a href="{{ route('admin.divisions.teachers.edit', $division) }}" class="btn btn-sm btn-outline-primary">Teachers</a>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#editDivision{{ $division->id }}">Rename</button>
                                                <form action="{{ route('admin.divisions.destroy', $division) }}" method="post" class="d-inline" data-confirm-title="Delete {{ $class->class_name }} - {{ $division->division_name }}?" data-confirm-body="Its teacher assignments are removed too." data-confirm-button="Delete Division" data-confirm-tone="danger">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">No divisions yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <form action="{{ route('admin.divisions.store') }}" method="post" class="form-inline">
                            @csrf
                            <input type="hidden" name="class_id" value="{{ $class->id }}">
                            <input type="text" name="division_name" class="form-control form-control-sm mr-2" placeholder="New division, e.g. C" maxlength="10" aria-label="New division name for {{ $class->class_name }}">
                            <button type="submit" class="btn btn-sm btn-primary">Add Division</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="editClass{{ $class->id }}" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <form class="modal-content" action="{{ route('admin.classes.update', $class) }}" method="post">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Rename {{ $class->class_name }}</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <input type="text" name="class_name" class="form-control" value="{{ $class->class_name }}" maxlength="10" aria-label="Class Name">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            @foreach ($class->divisions as $division)
                <div class="modal fade" id="editDivision{{ $division->id }}" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <form class="modal-content" action="{{ route('admin.divisions.update', $division) }}" method="post">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title">Rename {{ $class->class_name }} - {{ $division->division_name }}</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <input type="text" name="division_name" class="form-control" value="{{ $division->division_name }}" maxlength="10" aria-label="Division name">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        @empty
            <div class="col-12"><div class="alert alert-info">No classes yet. Add one to get started.</div></div>
        @endforelse
    </div>

    <div class="modal fade" id="addClass" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content" action="{{ route('admin.classes.store') }}" method="post">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Class</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <label for="class_name">Class Name</label>
                    <input type="text" id="class_name" name="class_name" class="form-control" placeholder="Grade 8" maxlength="10" value="{{ old('class_name') }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
@stop
