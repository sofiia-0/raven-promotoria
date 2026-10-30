@props(['preview','copy' => false])
<ul class="promoter-planning-preview-list">
    @foreach($preview['rows'] as $row)<li class="{{ $row['status'] === 'conflict' ? 'has-conflict' : '' }}"><div><strong>{{ $row['name'] }}@if($row['selection_identifier'] ?? null) <span class="promoter-picker-identifier">— {{ $row['selection_identifier'] }}</span>@endif</strong>@if($row['locality'])<span>{{ $row['locality'] }}</span>@endif @if($row['address'])<span>{{ $row['address'] }}</span>@endif
        <span>{{ \App\Enums\ActivityType::from($row['activity_type'])->label() }} · @if($copy){{ \Carbon\CarbonImmutable::parse($row['source_date'])->format('d/m/Y') }} → @endif{{ $row['date'] ? \Carbon\CarbonImmutable::parse($row['date'])->format('d/m/Y') : 'Sin fecha compatible' }}</span>
        @if($row['notes'])<span>{{ $row['notes'] }}</span>@endif</div><div><span class="raven-badge {{ $row['status'] === 'ready' ? 'raven-badge-success' : 'raven-badge-danger' }}">{{ $row['status'] === 'ready' ? 'Se incorporará' : ($row['status'] === 'unmappable' ? 'No se copiará' : 'Conflicto') }}</span>@if($row['message'])<p class="raven-field-help">{{ $row['message'] }}</p>@endif</div></li>@endforeach
</ul>
