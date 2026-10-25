{{-- Two-factor challenge (SEC-06). The auth-card layout has no sidebar, because every other page sends the user back here. --}}
@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('title', 'Enter Your Code')

@php($field = fn (string $name) => ['form-control', 'is-invalid' => $errors->has($name)])

@section('auth_header', 'Enter Your Code')

@section('auth_body')
    <p>Open your authenticator app and enter the 6-digit code that it shows.</p>

    <form action="{{ route('two-factor.verify') }}" method="post">
        @csrf

        <div class="form-group">
            <label for="two-factor-code">Code From Your App @include('partials.required')</label>
            <input id="two-factor-code" type="text" name="code" @class($field('code'))
                inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus
                @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
            @include('partials.field-error', ['name' => 'code'])
        </div>

        <button type="submit" class="btn btn-primary btn-block">Verify Code</button>
    </form>

    <h3 class="h6 mt-4">Lost Your Phone?</h3>
    <p class="text-muted small">Enter one of the recovery codes that you saved. Each code works one time.</p>

    <form action="{{ route('two-factor.verify') }}" method="post">
        @csrf

        <div class="form-group">
            <label for="two-factor-recovery-code">Recovery Code</label>
            <input id="two-factor-recovery-code" type="text" name="recovery_code" @class($field('recovery_code'))
                autocomplete="off" maxlength="12"
                @error('recovery_code') aria-invalid="true" aria-describedby="recovery-code-error" @enderror>
            @include('partials.field-error', ['name' => 'recovery_code'])
        </div>

        <button type="submit" class="btn btn-outline-secondary btn-block">Use Recovery Code</button>
    </form>
@stop

@section('auth_footer')
    <form action="{{ route('logout') }}" method="post" class="text-center">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm">Log Out</button>
    </form>
@stop
