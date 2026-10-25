{{-- Messages at the top of every role dashboard: flash messages, and the low recovery codes notice (SEC-06, FR-2FA-22). --}}
@include('partials.alerts')

@php($twoFactor = app(\App\Services\TwoFactorAuth::class))
@if (auth()->check() && $twoFactor->needsNewRecoveryCodes(auth()->user()))
    @php($remaining = $twoFactor->remainingRecoveryCodes(auth()->user()))
    <div class="alert alert-warning" role="status">
        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
        You have {{ $remaining }} recovery {{ Str::plural('code', $remaining) }} left.
        <a href="{{ route('security.index') }}">Make new codes on the Security page.</a>
    </div>
@endif
