<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn btn-outline-secondary fw-bold px-4']) }}>
    {{ $slot }}
</button>
