@extends('adminlte::page')

@php($editing = $teacher->exists)

@section('title', $editing ? 'Edit Teacher' : 'Add Teacher')

@php($field = fn (string $name) => ['form-control', 'is-invalid' => $errors->has($name)])

@section('content_header')
    <x-page-header :title="$editing ? 'Edit '.$teacher->full_name : 'Add Teacher'" />
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

    @php($selectedSubjects = collect(old('subject_ids', $teacher->subjects?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id))

    <form action="{{ $editing ? route('admin.teachers.update', $teacher) : route('admin.teachers.store') }}" method="post">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Profile</h2></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="first-name">First Name @include('partials.required')</label>
                                <input id="first-name" type="text" name="first_name" @class($field('first_name')) value="{{ old('first_name', $teacher->first_name) }}">
                            @include('partials.field-error', ['name' => 'first_name'])
                            </div>
                            <div class="form-group col-6">
                                <label for="last-name">Last Name @include('partials.required')</label>
                                <input id="last-name" type="text" name="last_name" @class($field('last_name')) value="{{ old('last_name', $teacher->last_name) }}">
                            @include('partials.field-error', ['name' => 'last_name'])
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="address-line-1">Address Line 1 @include('partials.required')</label>
                            <input id="address-line-1" type="text" name="address_line_1" @class($field('address_line_1')) value="{{ old('address_line_1', $teacher->address_line_1) }}">
                            @include('partials.field-error', ['name' => 'address_line_1'])
                        </div>
                        <div class="form-group">
                            <label for="address-line-2">Address Line 2</label>
                            <input id="address-line-2" type="text" name="address_line_2" @class($field('address_line_2')) value="{{ old('address_line_2', $teacher->address_line_2) }}">
                            @include('partials.field-error', ['name' => 'address_line_2'])
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="city">City @include('partials.required')</label>
                                <input id="city" type="text" name="city" @class($field('city')) value="{{ old('city', $teacher->city) }}">
                            @include('partials.field-error', ['name' => 'city'])
                            </div>
                            <div class="form-group col-6">
                                <label for="province">Province @include('partials.required')</label>
                                <input id="province" type="text" name="province" @class($field('province')) value="{{ old('province', $teacher->province) }}">
                            @include('partials.field-error', ['name' => 'province'])
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="country">Country @include('partials.required')</label>
                                <input id="country" type="text" name="country" @class($field('country')) value="{{ old('country', $teacher->country) }}">
                            @include('partials.field-error', ['name' => 'country'])
                            </div>
                            <div class="form-group col-6">
                                <label for="postal">Postal Code @include('partials.required')</label>
                                <input id="postal" type="text" name="postal" @class($field('postal')) value="{{ old('postal', $teacher->postal) }}">
                            @include('partials.field-error', ['name' => 'postal'])
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Login</h2></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="email">Email @include('partials.required')</label>
                            <input id="email" type="email" name="email" @class($field('email')) value="{{ old('email', $teacher->login?->email) }}">
                            @include('partials.field-error', ['name' => 'email'])
                        </div>
                        <div class="form-group mb-0">
                            <label for="password">Password @unless ($editing) @include('partials.required') @endunless</label>
                            <input id="password" type="password" name="password" @class($field('password')) autocomplete="new-password">
                            @include('partials.field-error', ['name' => 'password'])
                            @if ($editing)
                                <small class="form-text text-muted">Leave blank to keep the current password.</small>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2 class="card-title">Subjects @include('partials.required')</h2></div>
                    <div class="card-body">
                        @forelse ($subjects as $subject)
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="subject{{ $subject->id }}" name="subject_ids[]" value="{{ $subject->id }}" @checked($selectedSubjects->contains($subject->id))>
                                <label class="custom-control-label font-weight-normal" for="subject{{ $subject->id }}">{{ $subject->subject_name }}</label>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No subjects yet. <a href="{{ route('admin.subjects.index') }}">Add subjects</a> first.</p>
                        @endforelse
                        @include('partials.field-error', ['name' => 'subject_ids'])
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $editing ? 'Save Changes' : 'Add Teacher' }}</button>
    </form>

    @if ($editing)
        <div class="row mt-4">
            <div class="col-lg-6">
                @include('partials.account-status', [
                    'name' => $teacher->full_name,
                    'active' => (bool) $teacher->login?->is_active,
                    'action' => route('admin.teachers.toggle-active', $teacher),
                    'who' => 'teacher',
                ])
            </div>
        </div>
    @endif
@stop
