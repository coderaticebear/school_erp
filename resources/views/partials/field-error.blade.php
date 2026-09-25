{{-- Validation message shown directly under a field. Usage: @include('partials.field-error', ['name' => 'first_name']) --}}
@error($name)
    <div class="invalid-feedback d-block" id="{{ Str::slug($name) }}-error">{{ $message }}</div>
@enderror
