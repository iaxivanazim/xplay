<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-danger fw-bold px-4']) }}>
    {{ $slot }}
</button>
