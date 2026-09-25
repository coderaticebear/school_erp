@extends('adminlte::page')

@section('title', 'Edit Student')

@section('content_header')
    <h1>Edit {{ $student->first_name }} {{ $student->last_name }}</h1>
@stop

@section('content')
    @include('partials.alerts')

    @php($genders = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])
    @php($bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])

    <form action="{{ route('admin.students.update', $student) }}" method="post">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Student</h3></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>First Name</label>
                                <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $student->first_name) }}">
                            </div>
                            <div class="form-group col-6">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $student->last_name) }}">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-4">
                                <label>Date of Birth</label>
                                <input type="date" name="dob" class="form-control" value="{{ old('dob', $student->date_of_birth) }}">
                            </div>
                            <div class="form-group col-4">
                                <label>Gender</label>
                                <select name="gender" class="form-control">
                                    @foreach ($genders as $value => $label)
                                        <option value="{{ $value }}" @selected(old('gender', $student->gender) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-4">
                                <label>Blood Group</label>
                                <select name="blood_group" class="form-control">
                                    @foreach ($bloodGroups as $group)
                                        <option value="{{ $group }}" @selected(old('blood_group', $student->blood_group) === $group)>{{ $group }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label>Class / Division {{ $academicYear ? '('.$academicYear->year.')' : '' }}</label>
                            <select name="class_division_id" class="form-control">
                                <option value="">Select</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division->id }}" @selected((int) old('class_division_id', $currentDivisionId) === $division->id)>{{ $division->label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Login</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $student->login?->email) }}">
                        </div>
                        <div class="form-group mb-0">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" autocomplete="new-password">
                            <small class="form-text text-muted">Leave blank to keep the current password.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Address</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Address Line 1</label>
                            <input type="text" name="address_line_1" class="form-control" value="{{ old('address_line_1', $student->address_line_1) }}">
                        </div>
                        <div class="form-group">
                            <label>Address Line 2</label>
                            <input type="text" name="address_line_2" class="form-control" value="{{ old('address_line_2', $student->address_line_2) }}">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>City</label>
                                <input type="text" name="city" class="form-control" value="{{ old('city', $student->city) }}">
                            </div>
                            <div class="form-group col-6">
                                <label>Province</label>
                                <input type="text" name="province" class="form-control" value="{{ old('province', $student->province) }}">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6 mb-0">
                                <label>Country</label>
                                <input type="text" name="country" class="form-control" value="{{ old('country', $student->country) }}">
                            </div>
                            <div class="form-group col-6 mb-0">
                                <label>Postal Code</label>
                                <input type="text" name="postal" class="form-control" value="{{ old('postal', $student->postal) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('admin.students.show', $student) }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
@stop
