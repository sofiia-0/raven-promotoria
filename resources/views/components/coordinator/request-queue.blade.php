@props(['requests'])
<ul class="coordinator-list coordinator-queue">
    @forelse($requests as $item)
        <li wire:key="queue-{{ $item->id }}">
            <a href="{{ route('coordinator.requests.show', $item->id) }}" class="coordinator-queue-link">
                <span>
                    <strong class="coordinator-list-title">{{ $item->establishmentTitle() }}</strong>
                    <span class="coordinator-list-meta">{{ $item->promoter?->name ?? 'Promotora' }} · {{ $item->created_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y') }}</span>
                </span>
                <span class="raven-badge {{ $item->type === \App\Models\PharmacyRequest::TYPE_ALTA ? 'raven-badge-info' : 'raven-badge-rose' }}">{{ $item->type === \App\Models\PharmacyRequest::TYPE_ALTA ? 'Alta' : 'Baja' }}</span>
                <span aria-hidden="true">›</span>
            </a>
        </li>
    @empty
        <li class="coordinator-empty-note">No hay solicitudes pendientes.</li>
    @endforelse
</ul>
