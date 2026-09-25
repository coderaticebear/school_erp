@extends('adminlte::page')

@section('title', 'Add Student')

@section('content_header')
    <h1>Add Student</h1>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline">

            <form action="{{ route('admin.students.store') }}" method="post">
                @csrf

                <div class="card-body">

                    {{-- ALERTS --}}
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- TABS --}}
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" data-identify="1" data-toggle="tab" role="tab" href="#student_tab">Student Info</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-identify="2" data-toggle="tab" role="tab" href="#parent_tab">Parent Info</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-identify="3" data-toggle="tab" role="tab" href="#address_tab">Address & Login</a>
                        </li>
                    </ul>

                    <div class="tab-content pt-3">

                        {{-- STUDENT TAB --}}
                        <div class="tab-pane fade show active" id="student_tab">
                            <div class="form-group">
                                <label for="first-name">First Name</label>
                                <input id="first-name" type="text" name="first_name" value="{{ old('first_name') }}" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="last-name">Last Name</label>
                                <input id="last-name" type="text" name="last_name" value="{{ old('last_name') }}" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="dob">Date of Birth</label>
                                <input id="dob" type="date" name="dob" value="{{ old('dob') }}" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" class="form-control">
                                    <option value="">Select</option>
                                    <option value="male" @selected(old('gender') === 'male')>Male</option>
                                    <option value="female" @selected(old('gender') === 'female')>Female</option>
                                    <option value="other" @selected(old('gender') === 'other')>Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                
                                <label for="blood-group">Blood Group</label>
                                <select id="blood-group" name="blood_group" class="form-control">
                                    <option value="">Select</option>
                                    <option value="A+" @selected(old('blood_group') === 'A+')>A+</option><option value="A-" @selected(old('blood_group') === 'A-')>A-</option>
                                    <option value="B+" @selected(old('blood_group') === 'B+')>B+</option><option value="B-" @selected(old('blood_group') === 'B-')>B-</option>
                                    <option value="AB+" @selected(old('blood_group') === 'AB+')>AB+</option><option value="AB-" @selected(old('blood_group') === 'AB-')>AB-</option>
                                    <option value="O+" @selected(old('blood_group') === 'O+')>O+</option><option value="O-" @selected(old('blood_group') === 'O-')>O-</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="class-division-id">Class / Division</label>
                                <select id="class-division-id" name="class_division_id" class="form-control">
                                    <option value="">Select</option>
                                    @foreach ($divisions as $division)
                                        <option value="{{ $division->id }}" @selected((string) old('class_division_id') === (string) $division->id)>
                                            {{ $division->class->class_name ?? 'Class' }} - {{ $division->division_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- PARENT TAB --}}
                        <div class="tab-pane fade" id="parent_tab">

                            <div class="form-group">
                                <label for="p_email">Parent Email</label>
                                <input type="email" id="p_email" name="p_email" value="{{ old('p_email') }}" class="form-control">
                                <button id="check_p_email" class="badge badge-info mt-1">Find Parent</button>
                                <span id="p_found_badge" class="badge badge-success" style="display:none">Parent Found</span>
                                <span id="p_missing_badge" class="badge badge-danger" style="display:none">Parent Not Found</span>
                            </div>

                            <input type="hidden" name="parent_id" id="parent_id" value="{{ old('parent_id') }}">

                            <div id="parent_form" @style(['display:none' => ! $errors->any() || old('parent_id')])>
                                <div class="form-group">
                                    <label for="parent-first-name">Parent First Name</label>
                                    <input id="parent-first-name" type="text" name="parent_first_name" value="{{ old('parent_first_name') }}" class="form-control">
                                </div>

                                <div class="form-group">
                                    <label for="parent-last-name">Parent Last Name</label>
                                    <input id="parent-last-name" type="text" name="parent_last_name" value="{{ old('parent_last_name') }}" class="form-control">
                                </div>

                                <div class="form-row">
                                    <div class="col-4">
                                        <label for="parent-area-code">Area Code</label>
                                        <input id="parent-area-code" type="text" name="parent_area_code" value="{{ old('parent_area_code') }}" class="form-control">
                                    </div>
                                    <div class="col-8">
                                        <label for="parent-phone">Phone Number</label>
                                        <input id="parent-phone" type="text" name="parent_phone" value="{{ old('parent_phone') }}" class="form-control">
                                    </div>
                                </div>

                                <div class="form-group mt-2">
                                    <label for="parent-password">Password</label>
                                    <input id="parent-password" type="password" name="parent_password" class="form-control">
                                </div>
                            </div>
                        </div>

                        {{-- ADDRESS & LOGIN TAB --}}
                        <div class="tab-pane fade" id="address_tab">

                            <div class="form-group">
                                <label for="address-line-1">Address Line 1</label>
                                <input id="address-line-1" type="text" name="address_line_1" value="{{ old('address_line_1') }}" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="address-line-2">Address Line 2</label>
                                <input id="address-line-2" type="text" name="address_line_2" value="{{ old('address_line_2') }}" class="form-control">
                            </div>

                            <div class="form-row">
                                <div class="col">
                                    <label for="city">City</label>
                                    <input id="city" type="text" name="city" value="{{ old('city') }}" class="form-control">
                                </div>
                                <div class="col">
                                    <label for="province">Province</label>
                                    <input id="province" type="text" name="province" value="{{ old('province') }}" class="form-control">
                                </div>
                            </div>

                            <div class="form-row mt-2">
                                <div class="col">
                                    <label for="country">Country</label>
                                    <input id="country" type="text" name="country" value="{{ old('country') }}" class="form-control">
                                </div>
                                <div class="col">
                                    <label for="postal">Postal Code</label>
                                    <input id="postal" type="text" name="postal" value="{{ old('postal') }}" class="form-control">
                                </div>
                            </div>

                            <hr>

                            <div class="form-group">
                                <label for="email">Student Email</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="password">Student Password</label>
                                <input id="password" type="password" name="password" class="form-control">
                            </div>
                        </div>

                    </div>
                </div>

                <div class="card-footer">
                    
                    <button id="prev" type="button" class="btn btn-primary">Previous</button>
                    <button id="next" type="button" class="btn btn-primary">Next</button>
                    <button id="submit" type="submit" class="btn btn-primary">Add Student</button>
                </div>

            </form>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function () {

    $('#prev').hide();
    $('#next').show();
    $('#submit').hide();

    $('#next').on('click', function () {
    let $activeTab = $('.nav-tabs .nav-link.active');

    if ($activeTab.attr('href') === '#student_tab') {
        // Student → Parent
        $('a[href="#parent_tab"]').tab('show');
    } else if ($activeTab.attr('href') === '#parent_tab') {
        // Parent → Address
        $('a[href="#address_tab"]').tab('show');
    }
});

// PREV button
$('#prev').on('click', function () {
    let $activeTab = $('.nav-tabs .nav-link.active');

    if ($activeTab.attr('href') === '#address_tab') {
        // Address → Parent
        $('a[href="#parent_tab"]').tab('show');
    } else if ($activeTab.attr('href') === '#parent_tab') {
        // Parent → Student
        $('a[href="#student_tab"]').tab('show');
    }
});

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    let tab_value = $(e.target).data('identify');
        
        if (tab_value == 1) {
            $('#prev').hide();
            $('#next').show();
            $('#submit').hide();
        } else if(tab_value == 2) {
            $('#submit').hide();
            $('#prev').show();
            $('#next').show();
        } else if(tab_value == 3) {
            $('#submit').show();
            $('#prev').show();
            $('#next').hide();
        }
    })
    
    
    $('#check_p_email').click(function (e) {
        e.preventDefault();

        let p_email = $('#p_email').val();

        $.ajax({
            url: "/admin/getParentByEmail",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                email: p_email
            },
            success: function (data) {
                if (!data.parent_id) {
                    $('#p_missing_badge').show();
                    $('#p_found_badge').hide();
                    $('#parent_form').show();
                    $('#parent_id').val('');
                } else {
                    $('#p_missing_badge').hide();
                    $('#p_found_badge').show();
                    $('#parent_form').hide();
                    $('#parent_id').val(data.parent_id);
                }
            }
        });
    });

    

});
</script>
@stop
