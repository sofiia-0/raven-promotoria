@props(['city' => null, 'department' => null, 'neighborhood' => null, 'address' => null, 'reference' => null])
@php
    $locality = \App\Support\EstablishmentPresentation::locality($department, $city);
    $detail = \App\Support\EstablishmentPresentation::address($address, $neighborhood, $department, $city);
@endphp
<div class="promoter-location">
    <p class="promoter-locality">{{ $locality ?: 'Ubicación por completar' }}</p>
    <p class="promoter-street">{{ $detail ?: 'Dirección detallada por completar' }}</p>
</div>
