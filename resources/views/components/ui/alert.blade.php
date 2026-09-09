@props([
    'tone' => 'success',
])

@php
    $classes = match ($tone) {
        'success' => 'raven-alert raven-alert-success',
        'warning' => 'raven-alert raven-alert-warning',
        'danger' => 'raven-alert raven-alert-danger',
        'info' => 'raven-alert raven-alert-info',
        default => 'raven-alert raven-alert-info',
    };
@endendphp

<div
    role="alert"
    {{ $attributes->merge(['class' => $classes]) }}
>
    {{ $slot }}
</div>
