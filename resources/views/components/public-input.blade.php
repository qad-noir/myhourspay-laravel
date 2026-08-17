@props(['label', 'name', 'type' => 'text'])

<div class="auth-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <div class="auth-field__control">
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" {{ $attributes->except(['label'])->class(['auth-input', 'auth-input--error' => $errors->has($name)]) }} aria-describedby="{{ $errors->has($name) ? $name.'-error' : null }}">
        {{ $suffix ?? '' }}
    </div>
    @error($name)<p id="{{ $name }}-error" class="auth-error" role="alert">{{ $message }}</p>@enderror
</div>
