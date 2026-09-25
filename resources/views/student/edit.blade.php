@extends('adminlte::page')

@section('title', 'Edit Student')

@php($field = fn (string $name) => ['form-control', 'is-invalid' => $errors->has($name)])

@section('content_header')
    <x-page-header :title="'Edit '.$student->first_name.' '.$student->last_name" />
@stop

@section('content')
    @if (session('success') || session('error'))
        @include('partials.alerts')
    @elseif ($errors->any())
        <div class="alert alert-danger" role="alert">
            Please fix the {{ Str::plural('field', $errors->count()) }} marked below ({{ $errors->count() }}).
        </div>
    @endif

    <p class="text-muted"><span class="text-danger" aria-hidden="true">*</span> Required</p>

    @php($genders = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])
    @php($bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])

    <form action="{{ route('admin.students.update', $student) }}" method="post">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Student</h2></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="first-name">First Name @include('partials.required')</label>
                                <input id="first-name" type="text" name="first_name" @class($field('first_name')) value="{{ old('first_name', $student->first_name) }}">
                            @include('partials.field-error', ['name' => 'first_name'])
                            </div>
                            <div class="form-group col-6">
                                <label for="last-name">Last Name @include('partials.required')</label>
                                <input id="last-name" type="text" name="last_name" @class($field('last_name')) value="{{ old('last_name', $student->last_name) }}">
                            @include('partials.field-error', ['name' => 'last_name'])
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-4">
                                <label for="dob">Date of Birth @include('partials.required')</label>
                                <input id="dob" type="date" name="dob" @class($field('dob')) value="{{ old('dob', $student->date_of_birth) }}">
                            @include('partials.field-error', ['name' => 'dob'])
                            </div>
                            <div class="form-group col-4">
                                <label for="gender">Gender @include('partials.required')</label>
                                <select id="gender" name="gender" @class($field('gender'))>
                                    @foreach ($genders as $value => $label)
                                        <option value="{{ $value }}" @selected(old('gender', $student->gender) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @include('partials.field-error', ['name' => 'gender'])
                            </div>
                            <div class="form-group col-4">
                                <label for="blood-group">Blood Group @include('partials.required')</label>
                                <select id="blood-group" name="blood_group" @class($field('blood_group'))>
                                    @foreach ($bloodGroups as $group)
                                        <option value="{{ $group }}" @selected(old('blood_group', $student->blood_group) === $group)>{{ $group }}</option>
                                    @endforeach
                                </select>
                                @include('partials.field-error', ['name' => 'blood_group'])
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="class-division-id">Class / Division {{ $academicYear ? '('.$academicYear->year.')' : '' }} @include('partials.required')</label>
                            <select id="class-division-id" name="class_division_id" @class($field('class_division_id'))>
                                <option value="">Select</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division->id }}" @selected((int) old('class_division_id', $currentDivisionId) === $division->id)>{{ $division->label }}</option>
                                @endforeach
                            </select>
                                @include('partials.field-error', ['name' => 'class_division_id'])
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2 class="card-title">Login</h2></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="email">Email @include('partials.required')</label>
                            <input id="email" type="email" name="email" @class($field('email')) value="{{ old('email', $student->login?->email) }}">
                            @include('partials.field-error', ['name' => 'email'])
                        </div>
                        <div class="form-group mb-0">
                            <label for="password">Password</label>
                            <input id="password" type="password" name="password" @class($field('password')) autocomplete="new-password">
                            @include('partials.field-error', ['name' => 'password'])
                            <small class="form-text text-muted">Leave blank to keep the current password.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Address</h2></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="address-line-1">Address Line 1 @include('partials.required')</label>
                            <input id="address-line-1" type="text" name="address_line_1" @class($field('address_line_1')) value="{{ old('address_line_1', $student->address_line_1) }}">
                            @include('partials.field-error', ['name' => 'address_line_1'])
                        </div>
                        <div class="form-group">
                            <label for="address-line-2">Address Line 2</label>
                            <input id="address-line-2" type="text" name="address_line_2" @class($field('address_line_2')) value="{{ old('address_line_2', $student->address_line_2) }}">
                            @include('partials.field-error', ['name' => 'address_line_2'])
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="city">City @include('partials.required')</label>
                                <input id="city" type="text" name="city" @class($field('city')) value="{{ old('city', $student->city) }}">
                            @include('partials.field-error', ['name' => 'city'])
                            </div>
                            <div class="form-group col-6">
                                <label for="province">Province @include('partials.required')</label>
                                <input id="province" type="text" name="province" @class($field('province')) value="{{ old('province', $student->province) }}">
                            @include('partials.field-error', ['name' => 'province'])
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6 mb-0">
                                <label for="country">Country @include('partials.required')</label>
                                <input id="country" type="text" name="country" @class($field('country')) value="{{ old('country', $student->country) }}">
                            @include('partials.field-error', ['name' => 'country'])
                            </div>
                            <div class="form-group col-6 mb-0">
                                <label for="postal">Postal Code @include('partials.required')</label>
                                <input id="postal" type="text" name="postal" @class($field('postal')) value="{{ old('postal', $student->postal) }}">
                            @include('partials.field-error', ['name' => 'postal'])
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('admin.students.show', $student) }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>

    <div class="row mt-4">
        <div class="col-lg-6">
            @include('partials.account-status', [
                'name' => $student->first_name.' '.$student->last_name,
                'active' => (bool) $student->login?->is_active,
                'action' => route('admin.students.toggle-active', $student),
                'who' => 'student',
            ])
        </div>
    </div>
@stop
