{{-- Two-factor set-up (SEC-06). The auth-card layout has no sidebar, because every other page sends the user back here. --}}
@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('title', 'Set Up Two-Factor Sign-In')

@php($field = fn (string $name) => ['form-control', 'is-invalid' => $errors->has($name)])

@section('auth_header', 'Set Up Two-Factor Sign-In')

@section('auth_body')
    <p>To keep your account safe, you need a code from an app each time you sign in.</p>

    <ol class="pl-3">
        <li>Install an authenticator app on a phone or tablet.</li>
        <li>In the app, scan this QR code, or type the secret.</li>
        <li>Enter the 6-digit code that the app shows.</li>
    </ol>

    <p class="text-muted small">A parent or a school device can hold the authenticator app.</p>

    {{-- The SVG is made on our server by TwoFactorAuth::qrSvg() from our own data, so printing it unescaped is safe. This is the only unescaped output on the page. --}}
    <div class="two-factor-qr text-center mb-3" role="img" aria-label="QR code for your authenticator app">
        {!! $qrSvg !!}
    </div>

    <p class="mb-1" id="two-factor-secret-label">Can't scan the code? Type this secret into your app:</p>
    <p class="text-center"><code aria-labelledby="two-factor-secret-label">{{ implode(' ', str_split($secret, 4)) }}</code></p>

    <form action="{{ route('two-factor.enable') }}" method="post">
        @csrf

        <div class="form-group">
            <label for="two-factor-code">Code From Your App @include('partials.required')</label>
            <input id="two-factor-code" type="text" name="code" @class($field('code'))
                inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus
                @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
            @include('partials.field-error', ['name' => 'code'])
        </div>

        <button type="submit" class="btn btn-primary btn-block">Turn On Two-Factor</button>
    </form>
@stop

@section('auth_footer')
    <form action="{{ route('logout') }}" method="post" class="text-center">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm">Log Out</button>
    </form>
@stop
