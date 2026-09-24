@extends('adminlte::page')

@php($editing = $teacher->exists)

@section('title', $editing ? 'Edit Teacher' : 'Add Teacher')

@section('content_header')
    <h1>{{ $editing ? 'Edit '.$teacher->full_name : 'Add Teacher' }}</h1>
@stop

@section('content')
    @include('partials.alerts')

    @php($selectedSubjects = collect(old('subject_ids', $teacher->subjects?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id))

    <form action="{{ $editing ? route('admin.teachers.update', $teacher) : route('admin.teachers.store') }}" method="post">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Profile</h3></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>First Name</label>
                                <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $teacher->first_name) }}">
                            </div>
                            <div class="form-group col-6">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $teacher->last_name) }}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Address Line 1</label>
                            <input type="text" name="address_line_1" class="form-control" value="{{ old('address_line_1', $teacher->address_line_1) }}">
                        </div>
                        <div class="form-group">
                            <label>Address Line 2</label>
                            <input type="text" name="address_line_2" class="form-control" value="{{ old('address_line_2', $teacher->address_line_2) }}">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>City</label>
                                <input type="text" name="city" class="form-control" value="{{ old('city', $teacher->city) }}">
                            </div>
                            <div class="form-group col-6">
                                <label>Province</label>
                                <input type="text" name="province" class="form-control" value="{{ old('province', $teacher->province) }}">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>Country</label>
                                <input type="text" name="country" class="form-control" value="{{ old('country', $teacher->country) }}">
                            </div>
                            <div class="form-group col-6">
                                <label>Postal Code</label>
                                <input type="text" name="postal" class="form-control" value="{{ old('postal', $teacher->postal) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Login</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $teacher->login?->email) }}">
                        </div>
                        <div class="form-group mb-0">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" autocomplete="new-password">
                            @if ($editing)
                                <small class="form-text text-muted">Leave blank to keep the current password.</small>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Subjects</h3></div>
                    <div class="card-body">
                        @forelse ($subjects as $subject)
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="subject{{ $subject->id }}" name="subject_ids[]" value="{{ $subject->id }}" @checked($selectedSubjects->contains($subject->id))>
                                <label class="custom-control-label font-weight-normal" for="subject{{ $subject->id }}">{{ $subject->subject_name }}</label>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No subjects yet. <a href="{{ route('admin.subjects.index') }}">Add subjects</a> first.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('admin.teachers.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $editing ? 'Save Changes' : 'Add Teacher' }}</button>
    </form>
@stop
