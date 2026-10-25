{{-- Confirm Password (SEC-06): CSV exports, deactivation and two-factor resets ask for the password again.
     It uses the auth-card layout like the two-factor pages, because AdminLTE's lockscreen has no heading, an image
     with no alt text, an icon button with no name and low contrast in dark mode. --}}
@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@php($field = fn (string $name) => ['form-control', 'is-invalid' => $errors->has($name)])

@section('auth_header', 'Confirm Your Password')

@section('auth_body')
    <p>For your security, enter your password again to continue.</p>

    <form action="{{ route('password.confirm') }}" method="post">
        @csrf

        <div class="form-group">
            <label for="password">Your Password @include('partials.required')</label>
            <input id="password" type="password" name="password" @class($field('password'))
                autocomplete="current-password" required autofocus
                @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @include('partials.field-error', ['name' => 'password'])
        </div>

        <button type="submit" class="btn btn-primary btn-block">Confirm Password</button>
    </form>
@stop

@section('auth_footer')
    <p class="text-center mb-0">
        <a href="{{ route('password.request') }}">Forgot Your Password?</a>
    </p>
@stop
