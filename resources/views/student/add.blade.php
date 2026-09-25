@extends('adminlte::page')

@section('title', 'Add Student')

@section('content_header')
    <x-page-header title="Add Student" />
@stop

@php($genders = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])
@php($bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])
@php($field = fn (string $name) => ['form-control', 'is-invalid' => $errors->has($name)])
@php($newParent = ! old('parent_id') && ($errors->hasAny(['p_email', 'parent_first_name', 'parent_last_name', 'parent_area_code', 'parent_phone', 'parent_password']) || old('parent_first_name')))

@section('content')
    @if (session('success') || session('error'))
        @include('partials.alerts')
    @elseif ($errors->any())
        <div class="alert alert-danger" role="alert">
            Please fix the {{ Str::plural('field', $errors->count()) }} marked below ({{ $errors->count() }}).
        </div>
    @endif

    <p class="text-muted"><span class="text-danger" aria-hidden="true">*</span> Required</p>

    <form action="{{ route('admin.students.store') }}" method="post" novalidate>
        @csrf

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Student</h2></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-sm-6">
                                <label for="first-name">First Name @include('partials.required')</label>
                                <input type="text" id="first-name" name="first_name" value="{{ old('first_name') }}" @class($field('first_name')) aria-required="true" autocomplete="off">
                                @include('partials.field-error', ['name' => 'first_name'])
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="last-name">Last Name @include('partials.required')</label>
                                <input type="text" id="last-name" name="last_name" value="{{ old('last_name') }}" @class($field('last_name')) aria-required="true" autocomplete="off">
                                @include('partials.field-error', ['name' => 'last_name'])
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-sm-4">
                                <label for="dob">Date of Birth @include('partials.required')</label>
                                <input type="date" id="dob" name="dob" value="{{ old('dob') }}" max="{{ now()->subDay()->toDateString() }}" @class($field('dob')) aria-required="true">
                                @include('partials.field-error', ['name' => 'dob'])
                            </div>
                            <div class="form-group col-sm-4">
                                <label for="gender">Gender @include('partials.required')</label>
                                <select id="gender" name="gender" @class($field('gender')) aria-required="true">
                                    <option value="">Select</option>
                                    @foreach ($genders as $value => $label)
                                        <option value="{{ $value }}" @selected(old('gender') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @include('partials.field-error', ['name' => 'gender'])
                            </div>
                            <div class="form-group col-sm-4">
                                <label for="blood-group">Blood Group @include('partials.required')</label>
                                <select id="blood-group" name="blood_group" @class($field('blood_group')) aria-required="true">
                                    <option value="">Select</option>
                                    @foreach ($bloodGroups as $group)
                                        <option value="{{ $group }}" @selected(old('blood_group') === $group)>{{ $group }}</option>
                                    @endforeach
                                </select>
                                @include('partials.field-error', ['name' => 'blood_group'])
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="class-division-id">Class / Division @include('partials.required')</label>
                            <select id="class-division-id" name="class_division_id" @class($field('class_division_id')) aria-required="true">
                                <option value="">Select</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division->id }}" @selected((string) old('class_division_id') === (string) $division->id)>{{ $division->label }}</option>
                                @endforeach
                            </select>
                            @include('partials.field-error', ['name' => 'class_division_id'])
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2 class="card-title">Student Login</h2></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="email">Email @include('partials.required')</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" @class($field('email')) aria-required="true" autocomplete="off" placeholder="name@example.com">
                            @include('partials.field-error', ['name' => 'email'])
                        </div>
                        <div class="form-group mb-0">
                            <label for="password">Password @include('partials.required')</label>
                            <input type="password" id="password" name="password" @class($field('password')) aria-required="true" autocomplete="new-password" aria-describedby="password-hint">
                            <small id="password-hint" class="form-text text-muted">At least 8 characters.</small>
                            @include('partials.field-error', ['name' => 'password'])
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Parent / Guardian</h2></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="p-email">Parent Email @include('partials.required')</label>
                            <div class="input-group">
                                <input type="email" id="p-email" name="p_email" value="{{ old('p_email') }}" @class($field('p_email')) aria-required="true" autocomplete="off" placeholder="parent@example.com" aria-describedby="parent-lookup-status">
                                <div class="input-group-append">
                                    <button type="button" id="check_p_email" class="btn btn-outline-primary">Find Parent</button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Links the student to an existing parent, or add a new parent below.</small>
                            <div id="parent-lookup-status" class="mt-1" role="status" aria-live="polite">
                                @if (old('parent_id'))
                                    <span class="text-success"><i class="fas fa-check" aria-hidden="true"></i> Existing parent selected.</span>
                                @endif
                            </div>
                            @include('partials.field-error', ['name' => 'p_email'])
                            @include('partials.field-error', ['name' => 'parent_id'])
                        </div>

                        <input type="hidden" name="parent_id" id="parent_id" value="{{ old('parent_id') }}">

                        <div id="parent_form" @style(['display:none' => ! $newParent])>
                            <p class="text-muted small">No parent with this email yet. Add their details:</p>
                            <div class="form-row">
                                <div class="form-group col-sm-6">
                                    <label for="parent-first-name">Parent First Name @include('partials.required')</label>
                                    <input type="text" id="parent-first-name" name="parent_first_name" value="{{ old('parent_first_name') }}" @class($field('parent_first_name')) aria-required="true" autocomplete="off">
                                    @include('partials.field-error', ['name' => 'parent_first_name'])
                                </div>
                                <div class="form-group col-sm-6">
                                    <label for="parent-last-name">Parent Last Name @include('partials.required')</label>
                                    <input type="text" id="parent-last-name" name="parent_last_name" value="{{ old('parent_last_name') }}" @class($field('parent_last_name')) aria-required="true" autocomplete="off">
                                    @include('partials.field-error', ['name' => 'parent_last_name'])
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-4">
                                    <label for="parent-area-code">Area Code @include('partials.required')</label>
                                    <input type="tel" id="parent-area-code" name="parent_area_code" value="{{ old('parent_area_code') }}" @class($field('parent_area_code')) aria-required="true" inputmode="numeric" maxlength="10">
                                    @include('partials.field-error', ['name' => 'parent_area_code'])
                                </div>
                                <div class="form-group col-8">
                                    <label for="parent-phone">Phone Number @include('partials.required')</label>
                                    <input type="tel" id="parent-phone" name="parent_phone" value="{{ old('parent_phone') }}" @class($field('parent_phone')) aria-required="true" inputmode="numeric" maxlength="10">
                                    @include('partials.field-error', ['name' => 'parent_phone'])
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label for="parent-password">Parent Password @include('partials.required')</label>
                                <input type="password" id="parent-password" name="parent_password" @class($field('parent_password')) aria-required="true" autocomplete="new-password">
                                <small class="form-text text-muted">At least 8 characters. The parent uses this to sign in.</small>
                                @include('partials.field-error', ['name' => 'parent_password'])
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2 class="card-title">Address</h2></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="address-line-1">Address Line 1 @include('partials.required')</label>
                            <input type="text" id="address-line-1" name="address_line_1" value="{{ old('address_line_1') }}" @class($field('address_line_1')) aria-required="true" autocomplete="off">
                            @include('partials.field-error', ['name' => 'address_line_1'])
                        </div>
                        <div class="form-group">
                            <label for="address-line-2">Address Line 2</label>
                            <input type="text" id="address-line-2" name="address_line_2" value="{{ old('address_line_2') }}" @class($field('address_line_2')) autocomplete="off">
                            @include('partials.field-error', ['name' => 'address_line_2'])
                        </div>
                        <div class="form-row">
                            <div class="form-group col-sm-6">
                                <label for="city">City @include('partials.required')</label>
                                <input type="text" id="city" name="city" value="{{ old('city') }}" @class($field('city')) aria-required="true" autocomplete="off">
                                @include('partials.field-error', ['name' => 'city'])
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="province">Province @include('partials.required')</label>
                                <input type="text" id="province" name="province" value="{{ old('province') }}" @class($field('province')) aria-required="true" autocomplete="off">
                                @include('partials.field-error', ['name' => 'province'])
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-sm-6 mb-sm-0">
                                <label for="country">Country @include('partials.required')</label>
                                <input type="text" id="country" name="country" value="{{ old('country') }}" @class($field('country')) aria-required="true" autocomplete="off">
                                @include('partials.field-error', ['name' => 'country'])
                            </div>
                            <div class="form-group col-sm-6 mb-0">
                                <label for="postal">Postal Code @include('partials.required')</label>
                                <input type="text" id="postal" name="postal" value="{{ old('postal') }}" @class($field('postal')) aria-required="true" autocomplete="off">
                                @include('partials.field-error', ['name' => 'postal'])
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" id="submit" class="btn btn-primary">Add Student</button>
        </div>
    </form>
@stop

@section('js')
    <script>
        $(function () {
            const $status = $('#parent-lookup-status');
            const escape = (text) => $('<div>').text(text ?? '').html();

            $('#check_p_email').on('click', function () {
                const email = $('#p-email').val().trim();

                if (! email) {
                    $status.html('<span class="text-danger">Enter the parent\'s email first.</span>');
                    $('#p-email').trigger('focus');
                    return;
                }

                $status.text('Looking up…');

                $.post("{{ url('/admin/getParentByEmail') }}", { _token: "{{ csrf_token() }}", email: email })
                    .done(function (data) {
                        if (data.parent_id) {
                            $('#parent_id').val(data.parent_id);
                            $('#parent_form').hide();
                            $status.html('<span class="text-success"><i class="fas fa-check" aria-hidden="true"></i> Found: ' + escape(data.name) + '. The student will be linked to this parent.</span>');
                        } else {
                            $('#parent_id').val('');
                            $('#parent_form').show();
                            $status.html('<span class="text-warning"><i class="fas fa-user-plus" aria-hidden="true"></i> No parent with this email. Add their details below.</span>');
                            $('#parent-first-name').trigger('focus');
                        }
                    })
                    .fail(function () {
                        $status.html('<span class="text-danger">Enter a valid email address, then try again.</span>');
                    });
            });

            // Changing the email after a match clears the link, so the wrong parent is never attached.
            $('#p-email').on('input', function () {
                if ($('#parent_id').val()) {
                    $('#parent_id').val('');
                    $status.empty();
                }
            });

            // After a failed submit, bring the first problem into view.
            const $firstError = $('.is-invalid').first();
            if ($firstError.length) {
                $firstError.trigger('focus');
            }
        });
    </script>
@stop
