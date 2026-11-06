<details class="coordinator-notice" wire:poll.visible.60s>
    <summary aria-label="Avisos: {{ $pending }} solicitudes pendientes"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 17h14l-2-3V9a5 5 0 0 0-10 0v5l-2 3ZM10 20h4"/></svg><span>{{ $pending }}</span></summary>
    <div class="coordinator-notice-panel"><strong>Avisos de Coordinación</strong>
        @if($pending)<p>{{ $pending }} {{ $pending === 1 ? 'solicitud requiere' : 'solicitudes requieren' }} revisión.</p><a href="{{ route('coordinator.requests.index', ['estado' => \App\Models\PharmacyRequest::PENDING]) }}">Revisar solicitudes pendientes</a>@else<p>No hay solicitudes pendientes.</p>@endif
    </div>
</details>
