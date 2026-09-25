{{--
    Activate / deactivate a login, shown on Edit pages.
    Needs: $name (person's name), $active (bool), $action (toggle route URL), $who ("student" or "teacher").
--}}
<div class="card">
    <div class="card-header"><h2 class="card-title">Account</h2></div>
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center" style="gap: .75rem">
        <p class="mb-0">
            @if ($active)
                <strong>Active.</strong> {{ $name }} can sign in.
            @else
                <strong>Inactive.</strong> {{ $name }} can't sign in. Their records are kept.
            @endif
        </p>
        <form action="{{ $action }}" method="post" class="m-0"
              @if ($active) onsubmit="return confirm('Deactivate {{ $name }}? They will be signed out and can\'t sign in until reactivated. Their records are kept.')" @endif>
            @csrf
            @if ($active)
                <button type="submit" class="btn btn-outline-danger">Deactivate {{ ucfirst($who) }}</button>
            @else
                <button type="submit" class="btn btn-outline-primary">Reactivate {{ ucfirst($who) }}</button>
            @endif
        </form>
    </div>
</div>
