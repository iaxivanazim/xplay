@props(['messages'])

@if ($messages)
    <div {{ $attributes->merge(['class' => 'text-danger small mt-1']) }}>
        @foreach ((array) $messages as $message)
            <p class="mb-0">{{ $message }}</p>
        @endforeach
    </div>
@endif
