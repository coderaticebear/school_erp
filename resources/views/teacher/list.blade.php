@extends('adminlte::page')

@section('title', 'Teachers')

@section('content_header')
    <x-page-header title="Teachers">
        <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1" aria-hidden="true"></i> Add Teacher</a>
    </x-page-header>
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
                            <th class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teachers as $teacher)
                            <tr>
                                <td data-order="{{ $teacher->full_name }}">
                                    <button type="button" class="btn btn-link p-0 align-baseline view-button" data-url="{{ route('admin.teachers.show', $teacher) }}">{{ $teacher->full_name }}</button>
                                    @unless ($teacher->login?->is_active)
                                        <span class="badge badge-secondary ml-1">Inactive</span>
                                    @endunless
                                </td>
                                <td>{{ $teacher->login?->email }}</td>
                                <td>{{ $teacher->subjects->pluck('subject_name')->join(', ') ?: '—' }}</td>
                                <td>
                                    @foreach ($teacher->divisions as $division)
                                        <span @class(['badge', 'badge-primary' => $division->pivot->class_teacher, 'badge-light' => ! $division->pivot->class_teacher])>
                                            @if ($division->pivot->class_teacher)
                                                <i class="fas fa-star" aria-hidden="true"></i>
                                            @endif
                                            {{ $division->label }}
                                            @if ($division->pivot->class_teacher)
                                                <span class="sr-only">(Class Teacher)</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('admin.teachers.edit', $teacher) }}" class="btn btn-sm btn-outline-primary">
                                        Edit<span class="sr-only"> {{ $teacher->full_name }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-muted small mt-2 mb-0"><i class="fas fa-star" aria-hidden="true"></i> marks the division where the teacher is Class Teacher.</p>
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
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(function () {
            $('#teacherList').DataTable({ responsive: true, columnDefs: [{ targets: -1, orderable: false }] });

            const escape = (text) => $('<div>').text(text ?? '').html();

            $(document).on('click', '.view-button', function () {
                $.getJSON($(this).data('url'), function (teacher) {
                    const divisions = teacher.divisions.length
                        ? teacher.divisions.map(d => escape(d.label) + (d.class_teacher ? ' (Class Teacher)' : '')).join('<br>')
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
