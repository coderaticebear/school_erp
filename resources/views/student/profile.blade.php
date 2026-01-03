@extends('adminlte::page')

@section('title', 'Student Profile')

@section('content')
<div class="row">
    <!-- LEFT COLUMN -->
    <div class="col-md-4">
        <div class="card card-primary card-outline">
            <div class="card-body box-profile text-center">

                <img
                    class="profile-user-img img-fluid img-circle mb-3"
                    src="{{ asset('images/profile_picture.jpg') }}"
                    alt="Student profile picture"
                >

                <h3 class="profile-username">{{ $data['student_name'] }}</h3>
                <p class="text-muted">N/A</p>

                <ul class="list-group list-group-unbordered mb-3 text-left">
                    <li class="list-group-item">
                        <b>Student ID</b>
                        <span class="float-right">{{ $data['student_id'] }}</span>
                    </li>
                    <li class="list-group-item">
                        <b>Roll Number</b>
                        <span class="float-right">{{ $data['roll_number'] }}</span>
                    </li>
                    <li class="list-group-item">
                        <b>Status</b>
                        <span class="float-right text-success">Active</span>
                    </li>
                </ul>

                <a href="#" class="btn btn-primary btn-block">
                    Edit Student
                </a>
            </div>
        </div>

        <!-- PARENT DETAILS -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Parent / Guardian</h3>
            </div>
            <div class="card-body">
                <p><strong>Name:</strong> {{ $data['parent_name'] }}</p>
                <p><strong>Phone:</strong> N/A</p>
                <p><strong>Email:</strong> {{ $data['parent_email'] }}</p>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header p-2">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a class="nav-link active" href="#personal" data-toggle="tab">
                            Personal Info
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#academic" data-toggle="tab">
                            Academic
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#attendance" data-toggle="tab">
                            Attendance
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content">

                    <!-- PERSONAL INFO -->
                    <div class="active tab-pane" id="personal">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Date of Birth:</strong> {{ $data['date_of_birth']}}</p>
                                @if ($data['gender'] == 'male')
                                    <p><strong>Gender:</strong> Male</p>
                                @else
                                    <p><strong>Gender:</strong> Female</p>
                                @endif
                                <p><strong>Blood Group:</strong> {{ $data['blood_group'] }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Address:</strong></p>
                                <p class="text-muted">
                                    {{ $data['address_line_1'] }}<br>
                                    {{ $data['city'] }}, {{ $data['province'] }}<br>
                                    {{ $data['country'] }}<br>
                                    {{ $data['postal'] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- ACADEMIC INFO -->
                    <div class="tab-pane" id="academic">
                        <table class="table table-bordered">
                            <tr>
                                <th>Class</th>
                                <td>{{ $data['class_name'] }}</td>
                            </tr>
                            <tr>
                                <th>Section</th>
                                <td>{{ $data['division_name'] }}</td>
                            </tr>
                            <tr>
                                <th>Academic Year</th>
                                <td>{{ $data['academic_year'] }}</td>
                            </tr>
                            <tr>
                                <th>Class Teacher</th>
                                <td>No teacher has been assigned yet</td>
                            </tr>
                        </table>
                    </div>

                    <!-- ATTENDANCE -->
                    <div class="tab-pane" id="attendance">
                        <p><strong>Attendance Summary</strong></p>
                        <ul>
                            <li>No data found</li>

                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@stop
@section('css')
<style>
    .profile-user-img {
        max-width: 150px;
    }

    .list-group-item {
        font-size: 0.95rem;
    }
</style>
@stop
