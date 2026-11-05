<div class="coordinator-columns coordinator-operational" aria-label="Atención operativa">
    <section class="raven-card" aria-labelledby="oldest-requests-title">
        <div class="coordinator-section-head">
            <div>
                <h3 id="oldest-requests-title" class="raven-section-title">Solicitudes en espera</h3>
                <p class="raven-card-description">{{ $pendingAlta }} de alta · {{ $pendingBaja }} de baja</p>
            </div>
            <a class="coordinator-text-link" href="{{ route('coordinator.requests.index') }}">Ver todas</a>
        </div>
        <x-coordinator.request-queue :requests="$oldestRequests" />
    </section>
    <section class="raven-card" aria-labelledby="operational-title">
        <div class="coordinator-section-head">
            <div>
                <h3 id="operational-title" class="raven-section-title">Seguimiento operativo</h3>
                <p class="raven-card-description">{{ $monthLabel }} · Promotoras activas</p>
            </div>
        </div>
        <ul class="coordinator-list coordinator-queue">
            <li><a class="coordinator-queue-link" href="{{ route('coordinator.schedules.index') }}"><span><strong class="coordinator-list-title">Cronogramas enviados</strong><span class="coordinator-list-meta">{{ $schedulesDraft }} en borrador · {{ $schedulesMissing }} sin crear</span></span><strong>{{ $schedulesSent }}</strong><span aria-hidden="true">›</span></a></li>
            <li><a class="coordinator-queue-link" href="{{ route('coordinator.assignments.index', ['promotora' => 'none']) }}"><span><strong class="coordinator-list-title">Establecimientos sin responsable</strong><span class="coordinator-list-meta">Consulte las asignaciones de establecimientos activos</span></span><strong>{{ $unassigned }}</strong><span aria-hidden="true">›</span></a></li>
        </ul>
    </section>
</div>
