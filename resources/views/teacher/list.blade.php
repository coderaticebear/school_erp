@extends('adminlte::page')

@section('title', 'Teachers')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Teachers</h1>
        <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> Add Teacher</a>
    </div>
@stop

@section('content')
    @include('partials.alerts')

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="teacherList" class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subjects</th>
                            <th>Divisions</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teachers as $teacher)
                            <tr>
                                <td>{{ $teacher->full_name }}</td>
                                <td>{{ $teacher->login?->email }}</td>
                                <td>{{ $teacher->subjects->pluck('subject_name')->join(', ') ?: '—' }}</td>
                                <td>
                                    @foreach ($teacher->divisions as $division)
                                        <span @class(['badge', 'badge-primary' => $division->pivot->class_teacher, 'badge-light' => ! $division->pivot->class_teacher])
                                              @if ($division->pivot->class_teacher) title="Class teacher" @endif>{{ $division->label }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if ($teacher->login?->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-xs btn-outline-info view-button" data-url="{{ route('admin.teachers.show', $teacher) }}">View</button>
                                    <a href="{{ route('admin.teachers.edit', $teacher) }}" class="btn btn-xs btn-outline-primary">Edit</a>
                                    <form action="{{ route('admin.teachers.toggle-active', $teacher) }}" method="post" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-outline-{{ $teacher->login?->is_active ? 'warning' : 'success' }}">
                                            {{ $teacher->login?->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewDataModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewDataModalLabel"></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(function () {
            $('#teacherList').DataTable({ responsive: true });

            const escape = (text) => $('<div>').text(text ?? '').html();

            $(document).on('click', '.view-button', function () {
                $.getJSON($(this).data('url'), function (teacher) {
                    const divisions = teacher.divisions.length
                        ? teacher.divisions.map(d => escape(d.label) + (d.class_teacher ? ' (class teacher)' : '')).join('<br>')
                        : '—';

                    $('#viewDataModalLabel').text(teacher.fname + ' ' + teacher.lname);
                    $('#viewDataModal .modal-body').html(
                        '<dl class="row mb-0">' +
                        '<dt class="col-4">Email</dt><dd class="col-8">' + escape(teacher.email) + '</dd>' +
                        '<dt class="col-4">Status</dt><dd class="col-8">' + (teacher.active ? 'Active' : 'Inactive') + '</dd>' +
                        '<dt class="col-4">Subjects</dt><dd class="col-8">' + (teacher.subjects.map(escape).join(', ') || '—') + '</dd>' +
                        '<dt class="col-4">Divisions</dt><dd class="col-8">' + divisions + '</dd>' +
                        '</dl>'
                    );
                    $('#viewDataModal').modal('show');
                });
            });
        });
    </script>
@stop
