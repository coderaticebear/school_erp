{{-- The new recovery codes (SEC-06). They are shown on this page once and are never stored in clear text. --}}
@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('title', 'Your Recovery Codes')

@section('auth_header', 'Your Recovery Codes')

@section('auth_body')
    <div class="alert alert-success" role="status">Two-factor sign-in is on.</div>

    <p>If you lose your phone, you can sign in with one of these codes. Each code works one time.</p>
    <p><strong>This is the only time you will see these codes.</strong> Write them down or print them, and keep them in a safe place.</p>

    <ul class="list-unstyled two-factor-codes text-center">
        @foreach ($codes as $code)
            <li><code>{{ $code }}</code></li>
        @endforeach
    </ul>

    <div class="d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary" data-print>Print</button>
        <a href="{{ route('dashboard') }}" class="btn btn-primary">Go To Dashboard</a>
    </div>
@stop
