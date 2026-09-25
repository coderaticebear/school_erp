@extends('adminlte::page')

@section('title', 'Students')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Students</h1>
        <a href="{{ route('admin.addStudent') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> Add Student</a>
    </div>
@stop

@section('content')
    @include('partials.alerts')

    <div class="card">
        <div class="card-body">
            <table id="studentList" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Class</th>
                        <th>Parent</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $student)
                        <tr>
                            <td>{{ $student->first_name }}</td>
                            <td>{{ $student->last_name }}</td>
                            <td>{{ $student->StudentClasses->first()?->division?->label ?? 'Not assigned' }}</td>
                            <td>{{ trim(($student->parent->first_name ?? '').' '.($student->parent->last_name ?? '')) }}</td>
                            <td>
                                @if ($student->login?->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('admin.students.show', $student) }}" class="btn btn-xs btn-outline-info">View</a>
                                <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-xs btn-outline-primary">Edit</a>
                                <form action="{{ route('admin.students.toggle-active', $student) }}" method="post" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-outline-{{ $student->login?->is_active ? 'warning' : 'success' }}">
                                        {{ $student->login?->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(function () {
            $('#studentList').DataTable({ responsive: true });
        });
    </script>
@stop
