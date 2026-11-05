@props(['name', 'locality' => null, 'address' => null])
<div {{ $attributes->class(['coord-record-name']) }}>
    <strong>{{ $name }}</strong>
    @if(filled($locality))<span class="coord-record-locality"><x-promoter.icon name="location" />{{ $locality }}</span>@endif
    @if(filled($address))<span class="coord-record-address">{{ $address }}</span>@endif
</div>
