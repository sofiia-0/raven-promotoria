@props(['value'])

@php
    [$label, $icon] = match ($value) {
        'alta' => ['Alta', 'add'],
        'baja' => ['Baja', 'remove'],
        'pending' => ['Pendiente', 'clock'],
        'approved' => ['Aprobada', 'check'],
        'rejected' => ['Rechazada', 'remove'],
        default => ['Estado sin identificar', 'identity'],
    };
@endphp

<span {{ $attributes->class(['requests-badge']) }} data-tone="{{ $value }}"><x-promoter.icon :name="$icon" />{{ $label }}</span>
