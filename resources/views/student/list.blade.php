@extends('adminlte::page')

@section('title', 'Students')

@section('content_header')
    <x-page-header title="Students">
        <a href="{{ route('admin.addStudent') }}" class="btn btn-primary"><i class="fas fa-plus mr-1" aria-hidden="true"></i> Add Student</a>
    </x-page-header>
@stop

@section('content')
    @include('partials.alerts')

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="studentList" class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Parent / Guardian</th>
                            <th class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr>
                                <td data-order="{{ $student->first_name }} {{ $student->last_name }}">
                                    <a href="{{ route('admin.students.show', $student) }}">{{ $student->first_name }} {{ $student->last_name }}</a>
                                    @unless ($student->login?->is_active)
                                        <span class="badge badge-secondary ml-1">Inactive</span>
                                    @endunless
                                </td>
                                <td>{{ $student->StudentClasses->first()?->division?->label ?? 'Not assigned' }}</td>
                                <td>{{ trim(($student->parent->first_name ?? '').' '.($student->parent->last_name ?? '')) ?: '—' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-sm btn-outline-primary">
                                        Edit<span class="sr-only"> {{ $student->first_name }} {{ $student->last_name }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(function () {
            $('#studentList').DataTable({ responsive: true, columnDefs: [{ targets: -1, orderable: false }] });
        });
    </script>
@stop
