@extends('adminlte::page')

@section('content')
{{-- <div class="table-container">
    <table id="studentList">
    <thead>
        <tr>
            <th>First Name</th>
            <th>Last Name</th>

            <th>City</th>
            <th>Province</th>

            <th>Postal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $item)
            <tr>
                <td>{{ $item['first_name'] }}</td>
                <td>{{ $item['last_name'] }}</td>

                <td>{{ $item['city'] }}</td>
                <td>{{ $item['province'] }}</td>

                <td>{{ $item['postal'] }}</td>

            </tr>
        @endforeach
    </tbody>
</table>
</div> --}}


<div class="row">
    @foreach ($data as $item)
        <div class="col-sm-3">
             <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">{{$item['sfname']." ".$item['slname'] }}</h3>
            <div class="card-tools">
            <!-- Buttons, labels, and many other things can be placed here! -->
            <!-- Here is a label for example -->
            <span class="badge badge-primary">Active</span>
            <span class="badge badge-danger">Delete</span>
            </div>
            <!-- /.card-tools -->
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            Parent/Guardian: {{ $item['pfname']." ".$item['plname'] }}
        </div>
        <!-- /.card-body -->
        <div class="card-footer">
            <button type="button" class="btn btn-block btn-outline-primary">View Student</button>
        </div>
        <!-- /.card-footer -->
        </div>
    <!-- /.card -->
        </div>
    @endforeach
</div>
@stop

@section('js')
    <script>



    </script>
@stop
