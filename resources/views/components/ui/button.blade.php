@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
    $classes = match ($variant) {
        'primary' => 'raven-button raven-button-primary',
        'secondary' => 'raven-button raven-button-secondary',
        'danger' => 'raven-button raven-button-danger',
        'ghost' => 'raven-button raven-button-ghost',
        default => 'raven-button raven-button-primary',
    };
@endphp

@if ($href)

    <a
        href="{{ $href }}"
        {{ $attributes->merge(['class' => $classes]) }}
    >
        {{ $slot }}
    </a>

@else

    <button
        type="{{ $type }}"
        {{ $attributes->merge(['class' => $classes]) }}
    >
        {{ $slot }}
    </button>

@endif
