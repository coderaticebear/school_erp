@extends('adminlte::page')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h1>Students</h1>
                    <a href="{{ url('admin/addStudent') }}" class="badge badge-primary">Add Student</a>
                    <a class="badge badge-primary">Bulk Add Student</a>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table id="studentList" class="table table-bordered table-hover dataTable dtr-inline">
                            <thead>
                                <tr>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Parent</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data as $item)
                                    <tr>
                                        <td>{{ $item['sfname'] }}</td>
                                        <td>{{ $item['slname'] }}</td>
                                        <td>{{ $item['pfname'] . ' ' . $item['plname'] }}</td>
                                        <td>
                                            <button class="badge badge-success">Active</button>
                                            <button class="badge badge-primary">View/Edit</button>

                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            $('#studentList').DataTable({
                responsive: true,
            });
        })
    </script>
@stop
