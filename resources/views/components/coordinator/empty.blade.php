@props(['title', 'description' => null, 'kind' => 'search'])
<div {{ $attributes->class(['coord-empty']) }}>
    <x-coordinator.illustration :kind="$kind" />
    <div>
        <p class="raven-card-title">{{ $title }}</p>
        @if($description)<p class="raven-card-description">{{ $description }}</p>@endif
        {{ $slot }}
    </div>
</div>
