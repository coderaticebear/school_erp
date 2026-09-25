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
                    <div class="card-header"><h2 class="card-title">Student</h2></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="first-name">First Name</label>
                                <input id="first-name" type="text" name="first_name" class="form-control" value="{{ old('first_name', $student->first_name) }}">
                            </div>
                            <div class="form-group col-6">
                                <label for="last-name">Last Name</label>
                                <input id="last-name" type="text" name="last_name" class="form-control" value="{{ old('last_name', $student->last_name) }}">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-4">
                                <label for="dob">Date of Birth</label>
                                <input id="dob" type="date" name="dob" class="form-control" value="{{ old('dob', $student->date_of_birth) }}">
                            </div>
                            <div class="form-group col-4">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" class="form-control">
                                    @foreach ($genders as $value => $label)
                                        <option value="{{ $value }}" @selected(old('gender', $student->gender) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-4">
                                <label for="blood-group">Blood Group</label>
                                <select id="blood-group" name="blood_group" class="form-control">
                                    @foreach ($bloodGroups as $group)
                                        <option value="{{ $group }}" @selected(old('blood_group', $student->blood_group) === $group)>{{ $group }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="class-division-id">Class / Division {{ $academicYear ? '('.$academicYear->year.')' : '' }}</label>
                            <select id="class-division-id" name="class_division_id" class="form-control">
                                <option value="">Select</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division->id }}" @selected((int) old('class_division_id', $currentDivisionId) === $division->id)>{{ $division->label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card card-primary">
                    <div class="card-header"><h2 class="card-title">Login</h2></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $student->login?->email) }}">
                        </div>
                        <div class="form-group mb-0">
                            <label for="password">Password</label>
                            <input id="password" type="password" name="password" class="form-control" autocomplete="new-password">
                            <small class="form-text text-muted">Leave blank to keep the current password.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header"><h2 class="card-title">Address</h2></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="address-line-1">Address Line 1</label>
                            <input id="address-line-1" type="text" name="address_line_1" class="form-control" value="{{ old('address_line_1', $student->address_line_1) }}">
                        </div>
                        <div class="form-group">
                            <label for="address-line-2">Address Line 2</label>
                            <input id="address-line-2" type="text" name="address_line_2" class="form-control" value="{{ old('address_line_2', $student->address_line_2) }}">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="city">City</label>
                                <input id="city" type="text" name="city" class="form-control" value="{{ old('city', $student->city) }}">
                            </div>
                            <div class="form-group col-6">
                                <label for="province">Province</label>
                                <input id="province" type="text" name="province" class="form-control" value="{{ old('province', $student->province) }}">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6 mb-0">
                                <label for="country">Country</label>
                                <input id="country" type="text" name="country" class="form-control" value="{{ old('country', $student->country) }}">
                            </div>
                            <div class="form-group col-6 mb-0">
                                <label for="postal">Postal Code</label>
                                <input id="postal" type="text" name="postal" class="form-control" value="{{ old('postal', $student->postal) }}">
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
