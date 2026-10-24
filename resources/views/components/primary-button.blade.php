<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-warning fw-bold px-4']) }}>
    {{ $slot }}
</button>
