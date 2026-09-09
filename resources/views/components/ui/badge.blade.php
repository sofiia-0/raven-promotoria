@props([
    'tone' => 'neutral',
])

@php
    $classes = match ($tone) {
        'success' => 'raven-badge raven-badge-success',
        'info' => 'raven-badge raven-badge-info',
        'warning' => 'raven-badge raven-badge-warning',
        'danger' => 'raven-badge raven-badge-danger',
        'rose' => 'raven-badge raven-badge-rose',
        default => 'raven-badge raven-badge-neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
