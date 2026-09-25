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
                    <div class="card-header"><h2 class="card-title">Profile</h2></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="first-name">First Name</label>
                                <input id="first-name" type="text" name="first_name" class="form-control" value="{{ old('first_name', $teacher->first_name) }}">
                            </div>
                            <div class="form-group col-6">
                                <label for="last-name">Last Name</label>
                                <input id="last-name" type="text" name="last_name" class="form-control" value="{{ old('last_name', $teacher->last_name) }}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="address-line-1">Address Line 1</label>
                            <input id="address-line-1" type="text" name="address_line_1" class="form-control" value="{{ old('address_line_1', $teacher->address_line_1) }}">
                        </div>
                        <div class="form-group">
                            <label for="address-line-2">Address Line 2</label>
                            <input id="address-line-2" type="text" name="address_line_2" class="form-control" value="{{ old('address_line_2', $teacher->address_line_2) }}">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="city">City</label>
                                <input id="city" type="text" name="city" class="form-control" value="{{ old('city', $teacher->city) }}">
                            </div>
                            <div class="form-group col-6">
                                <label for="province">Province</label>
                                <input id="province" type="text" name="province" class="form-control" value="{{ old('province', $teacher->province) }}">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="country">Country</label>
                                <input id="country" type="text" name="country" class="form-control" value="{{ old('country', $teacher->country) }}">
                            </div>
                            <div class="form-group col-6">
                                <label for="postal">Postal Code</label>
                                <input id="postal" type="text" name="postal" class="form-control" value="{{ old('postal', $teacher->postal) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header"><h2 class="card-title">Login</h2></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $teacher->login?->email) }}">
                        </div>
                        <div class="form-group mb-0">
                            <label for="password">Password</label>
                            <input id="password" type="password" name="password" class="form-control" autocomplete="new-password">
                            @if ($editing)
                                <small class="form-text text-muted">Leave blank to keep the current password.</small>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card card-primary">
                    <div class="card-header"><h2 class="card-title">Subjects</h2></div>
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
