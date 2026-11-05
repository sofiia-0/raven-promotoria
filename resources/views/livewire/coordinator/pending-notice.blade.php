<details class="coordinator-notice" wire:poll.visible.60s x-data @click.outside="$el.open = false" @keydown.escape.stop="$el.open = false; $refs.trigger.focus()">
    <summary x-ref="trigger" aria-label="Avisos: {{ $pending }} {{ $pending === 1 ? 'solicitud pendiente' : 'solicitudes pendientes' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 17h14l-2-3V9a5 5 0 0 0-10 0v5l-2 3ZM10 20h4"/></svg><span>{{ $pending }}</span></summary>
    <div class="coordinator-notice-panel">
        <div class="coordinator-section-head"><strong>Solicitudes pendientes</strong><span class="raven-badge raven-badge-info">{{ $pending }}</span></div>
        <x-coordinator.request-queue :requests="$oldestRequests" />
        <a class="coordinator-text-link" href="{{ route('coordinator.requests.index', ['estado' => \App\Models\PharmacyRequest::PENDING]) }}">Ver todas las solicitudes</a>
    </div>
</details>
