@props(['city' => null, 'department' => null, 'neighborhood' => null, 'address' => null, 'reference' => null])
@php
    $locality = collect([$department, $city])->filter(fn ($part) => is_string($part) && trim($part) !== '')->map(fn ($part) => trim($part))->join(', ');
    $geography = collect([$department, $city])->map(fn ($part) => \App\Support\EstablishmentSearch::normalize(trim((string) $part)));
    $showNeighborhood = $neighborhood && !$geography->contains(\App\Support\EstablishmentSearch::normalize(trim($neighborhood)));
@endphp
<div class="promoter-location">
    <p class="promoter-locality">{{ $locality ?: 'Ubicación por completar' }}</p>
    @if($showNeighborhood)<p class="promoter-street">{{ $neighborhood }}</p>@endif
    @if($address)<p class="promoter-street">{{ $address }}</p>@endif
    @if($reference)<p class="promoter-reference">Referencia: {{ $reference }}</p>@endif
</div>
