@props(['label'])
<details {{ $attributes->class(['raven-card', 'coord-filter-disclosure']) }} x-data x-init="$el.open = matchMedia('(min-width: 821px)').matches">
    <summary><x-promoter.icon name="search" /> {{ $label }}</summary>
    {{ $slot }}
</details>
