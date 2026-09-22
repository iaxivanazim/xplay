@props(['value'])

<label {{ $attributes->merge(['class' => 'form-label fw-bold text-light']) }}>
    {{ $value ?? $slot }}
</label>
