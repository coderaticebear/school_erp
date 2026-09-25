@extends('adminlte::page')

@section('title', 'Academic Years')

@section('content_header')
    <x-page-header title="Academic Years" />
@stop

@section('content')
    @include('partials.alerts')

    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h2 class="card-title">Add Academic Year</h2></div>
                <form action="{{ route('admin.academic-years.store') }}" method="post">
                    @csrf
                    <div class="card-body">
                        <div class="form-group mb-0">
                            <label for="year">Year</label>
                            <input type="text" id="year" name="year" class="form-control" placeholder="2026-2027" value="{{ old('year') }}">
                            <small class="form-text text-muted">The first year you add becomes active automatically.</small>
                        </div>
                    </div>
                    <div class="card-footer"><button type="submit" class="btn btn-primary">Add Year</button></div>
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
                                    <th>Year</th>
                                    <th>Status</th>
                                    <th>Enrolments</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($academicYears as $academicYear)
                                    <tr>
                                        <td>{{ $academicYear->year }}</td>
                                        <td>
                                            @if ($academicYear->is_active)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>{{ $academicYear->student_class_count }}</td>
                                        <td class="text-right text-nowrap">
                                            @unless ($academicYear->is_active)
                                                <form action="{{ route('admin.academic-years.activate', $academicYear) }}" method="post" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success">Make Active</button>
                                                </form>
                                            @endunless
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editYear{{ $academicYear->id }}">Edit</button>
                                            @unless ($academicYear->is_active)
                                                <form action="{{ route('admin.academic-years.destroy', $academicYear) }}" method="post" class="d-inline" data-confirm-title="Delete {{ $academicYear->year }}?" data-confirm-body="This academic year has no enrolments, exams, attendance or timetable, so nothing else is affected." data-confirm-button="Delete Year" data-confirm-tone="danger">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">No academic years yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($academicYears as $academicYear)
        <div class="modal fade" id="editYear{{ $academicYear->id }}" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <form class="modal-content" action="{{ route('admin.academic-years.update', $academicYear) }}" method="post">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit {{ $academicYear->year }}</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <label for="year-{{ $academicYear->id }}">Year</label>
                        <input id="year-{{ $academicYear->id }}" type="text" name="year" class="form-control" value="{{ $academicYear->year }}">
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
