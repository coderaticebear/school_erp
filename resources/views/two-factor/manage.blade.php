{{-- The Security page (SEC-06): two-factor status and new recovery codes. New codes are shown here once, from the flash. --}}
@extends('adminlte::page')

@section('title', 'Security')

@section('content_header')
    <x-page-header title="Security" subtitle="Two-factor sign-in and recovery codes" />
@stop

@section('content')
    @include('partials.alerts')

    @if ($newCodes !== [])
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your New Recovery Codes</h2>
            </div>
            <div class="card-body">
                <p>Your old recovery codes do not work now. If you lose your phone, you can sign in with one of these codes. Each code works one time.</p>
                <p><strong>This is the only time you will see these codes.</strong> Write them down or print them, and keep them in a safe place.</p>

                <ul class="list-unstyled">
                    @foreach ($newCodes as $code)
                        <li><code>{{ $code }}</code></li>
                    @endforeach
                </ul>

                <button type="button" class="btn btn-outline-secondary no-print" data-print><i class="fas fa-print mr-1" aria-hidden="true"></i> Print</button>
            </div>
        </div>
    @endif

    <div class="card no-print">
        <div class="card-header">
            <h2 class="card-title">Two-Factor Sign-In</h2>
        </div>
        <div class="card-body">
            @if ($login->hasTwoFactorEnabled())
                <p>
                    <i class="fas fa-check-circle text-success" aria-hidden="true"></i>
                    Two-factor sign-in is on. You turned it on {{ $login->two_factor_confirmed_at->format('M j, Y') }}.
                </p>
                <p>You have {{ $remaining }} unused recovery {{ Str::plural('code', $remaining) }}.</p>
                <p class="text-muted small">New codes replace all your old codes. You must enter your password first.</p>

                <form action="{{ route('security.recovery-codes') }}" method="post"
                    data-confirm-title="Make new recovery codes?"
                    data-confirm-body="Your old recovery codes will stop working."
                    data-confirm-button="Make New Codes">
                    @csrf
                    <button type="submit" class="btn btn-primary">Make New Recovery Codes</button>
                </form>
            @else
                <p>
                    <i class="fas fa-exclamation-circle text-muted" aria-hidden="true"></i>
                    Two-factor sign-in is off.
                </p>
                <a href="{{ route('two-factor.setup') }}" class="btn btn-primary">Set Up Two-Factor Sign-In</a>
            @endif
        </div>
    </div>
@stop
