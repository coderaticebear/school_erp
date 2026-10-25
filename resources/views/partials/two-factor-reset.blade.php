{{--
    Reset a lost two-factor device (SEC-06), shown on Edit pages. The route asks for the admin's password first.
    Needs: $title (card title), $name (person's name), $enabled (bool, two-factor is on), $action (reset route URL).
    Optional: $intro (text above the status, e.g. the parent's name and email).
--}}
<div class="card">
    <div class="card-header"><h2 class="card-title">{{ $title }}</h2></div>
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center" style="gap: .75rem">
        <div>
            @isset($intro)
                <p class="mb-1">{{ $intro }}</p>
            @endisset
            <p class="mb-0">
                @if ($enabled)
                    <strong>Two-factor sign-in is on.</strong> Reset it only when {{ $name }} lost the device with the app.
                @else
                    <strong>Two-factor sign-in is not set up.</strong> {{ $name }} sets it up at the next sign-in.
                @endif
            </p>
        </div>
        @if ($enabled)
            <form action="{{ $action }}" method="post" class="m-0"
                  data-confirm-title="Reset two-factor for {{ $name }}?"
                  data-confirm-body="They are signed out everywhere and must set up two-factor again at their next sign-in. You may be asked for your password."
                  data-confirm-button="Reset Two-Factor" data-confirm-tone="danger">
                @csrf
                <button type="submit" class="btn btn-outline-danger">Reset Two-Factor<span class="sr-only"> for {{ $name }}</span></button>
            </form>
        @endif
    </div>
</div>
