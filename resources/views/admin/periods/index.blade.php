@extends('adminlte::page')

@section('title', 'Bell Schedule')

@section('content_header')
    <h1>Bell Schedule</h1>
@stop

@section('content')
    @include('partials.alerts')

    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title">Add Period</h3></div>
                <form action="{{ route('admin.periods.store') }}" method="post">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="label">Label</label>
                            <input type="text" id="label" name="label" class="form-control" value="{{ old('label') }}" placeholder="Period 7">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="starts_at">Starts</label>
                                <input type="time" id="starts_at" name="starts_at" class="form-control" value="{{ old('starts_at') }}">
                            </div>
                            <div class="form-group col-6">
                                <label for="ends_at">Ends</label>
                                <input type="time" id="ends_at" name="ends_at" class="form-control" value="{{ old('ends_at') }}">
                            </div>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="is_break" name="is_break" value="1" @checked(old('is_break'))>
                            <label class="custom-control-label font-weight-normal" for="is_break">This is a break (no lessons)</label>
                        </div>
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
                                <th>Period</th>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Lessons</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($periods as $period)
                                <tr>
                                    <td>{{ $period->label }}</td>
                                    <td>{{ $period->time_range }}</td>
                                    <td>
                                        @if ($period->is_break)
                                            <span class="badge badge-secondary">Break</span>
                                        @else
                                            <span class="badge badge-info">Teaching</span>
                                        @endif
                                    </td>
                                    <td>{{ $period->entries_count }}</td>
                                    <td class="text-right text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editPeriod{{ $period->id }}">Edit</button>
                                        <form action="{{ route('admin.periods.destroy', $period) }}" method="post" class="d-inline" onsubmit="return confirm('Delete {{ $period->label }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No periods yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="text-muted small">School days: {{ implode(', ', \App\Services\TimetableGrid::days()) }} (set <code>SCHOOL_DAYS</code> in <code>.env</code> to change).</p>
        </div>
    </div>

    @foreach ($periods as $period)
        <div class="modal fade" id="editPeriod{{ $period->id }}" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <form class="modal-content" action="{{ route('admin.periods.update', $period) }}" method="post">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit {{ $period->label }}</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Label</label>
                            <input type="text" name="label" class="form-control" value="{{ $period->label }}">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>Starts</label>
                                <input type="time" name="starts_at" class="form-control" value="{{ substr($period->starts_at, 0, 5) }}">
                            </div>
                            <div class="form-group col-6">
                                <label>Ends</label>
                                <input type="time" name="ends_at" class="form-control" value="{{ substr($period->ends_at, 0, 5) }}">
                            </div>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="is_break{{ $period->id }}" name="is_break" value="1" @checked($period->is_break)>
                            <label class="custom-control-label font-weight-normal" for="is_break{{ $period->id }}">This is a break (no lessons)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@stop
