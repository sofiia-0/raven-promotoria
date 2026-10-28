@props(['city' => null, 'department' => null, 'neighborhood' => null, 'address' => null])
@php
    $locality = collect([$city, $department])->filter(fn ($part) => is_string($part) && trim($part) !== '')->map(fn ($part) => trim($part))->unique(fn ($part) => mb_strtolower($part))->join(' · ');
@endphp
<div class="promoter-location">
    <p class="promoter-locality">{{ $locality ?: 'Ubicación por completar' }}</p>
    @if($neighborhood || $address)
        <p class="promoter-street">{{ collect([$neighborhood, $address])->filter()->unique()->join(' · ') }}</p>
    @endif
</div>
