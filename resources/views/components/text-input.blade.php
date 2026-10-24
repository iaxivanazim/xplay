@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'form-control bg-dark text-white border-secondary focus:border-warning focus:ring-0 shadow-none']) }}>
